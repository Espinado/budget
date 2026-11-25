<?php

namespace App\Services\Budget;

use App\Enums\CashflowType;
use App\Models\Account;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\DebtInstallment;
use App\Models\Household;
use App\Models\PlanLine;
use App\Models\PlanTemplate;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class PeriodGenerator
{
    public int $lastCopiedCount = 0;

    public function __construct(private PeriodCalendar $calendar) {}

    public function periodFor(Household $household, CarbonInterface $date): BudgetPeriod
    {
        $day = CarbonImmutable::parse($date->toDateString());
        $existing = $this->containing($household, $day);

        if ($existing) {
            return $existing;
        }

        $latest = $household->periods()->orderByDesc('ends_on')->first();
        $earliest = $household->periods()->orderBy('starts_on')->first();

        if ($latest === null || $earliest === null) {
            [$start, $end] = $this->calendar->boundsFor($day, $household->anchor_day);

            return $this->createFromBounds($household, $start, $end);
        }

        if ($day->toDateString() > $latest->ends_on->toDateString()) {
            $cursor = $latest;

            while ($cursor->ends_on->toDateString() < $day->toDateString()) {
                $cursor = $this->periodAfter($cursor);
            }

            return $cursor;
        }

        if ($day->toDateString() < $earliest->starts_on->toDateString()) {
            $cursor = $earliest;

            while ($cursor->starts_on->toDateString() > $day->toDateString()) {
                $cursor = $this->periodBefore($cursor);
            }

            return $cursor;
        }

        return $this->fillGap($household, $day);
    }

    public function periodAfter(BudgetPeriod $period): BudgetPeriod
    {
        $period->loadMissing('household');
        $start = CarbonImmutable::parse($period->ends_on)->addDay();
        $existing = $this->containing($period->household, $start);

        if ($existing) {
            return $existing;
        }

        $naturalEnd = $this->calendar->boundsFor($start, $period->household->anchor_day)[1];
        $next = BudgetPeriod::query()
            ->where('household_id', $period->household_id)
            ->whereDate('starts_on', '>', $period->ends_on->toDateString())
            ->orderBy('starts_on')
            ->first();

        $end = $naturalEnd;
        $custom = false;

        if ($next && $next->starts_on->toDateString() <= $naturalEnd->toDateString()) {
            $end = CarbonImmutable::parse($next->starts_on)->subDay();
            $custom = true;
        }

        if ($end->toDateString() < $start->toDateString()) {
            throw new InvalidArgumentException(__('Нельзя построить следующий период: соседний период перекрывает дату.'));
        }

        return $this->createFromBounds($period->household, $start, $end, $custom);
    }

    public function copyPlanOntoNext(BudgetPeriod $period): BudgetPeriod
    {
        $next = $this->periodAfter($period);
        $lines = $period->planLines()->with(['category', 'account'])->orderBy('id')->get();
        $this->lastCopiedCount = 0;
        $debtLineIds = DebtInstallment::query()
            ->whereIn('plan_line_id', $lines->modelKeys())
            ->pluck('plan_line_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $linkedIds = PlanLine::query()
            ->where('period_id', $next->id)
            ->whereNotNull('source_line_id')
            ->pluck('source_line_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $unlinked = PlanLine::query()
            ->where('period_id', $next->id)
            ->whereNull('source_line_id')
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            if (in_array((int) $line->id, $debtLineIds, true) || in_array((int) $line->id, $linkedIds, true)) {
                continue;
            }

            // Same category + title absorbs template shells (often 0 €) instead of duplicating.
            $match = $unlinked->first(fn (PlanLine $existing) => (int) $existing->category_id === (int) $line->category_id
                && $existing->title === $line->title);

            $expectedOn = $this->dateInPeriod(
                $line->expected_on ? CarbonImmutable::parse($line->expected_on)->addMonthNoOverflow() : null,
                $next,
            );

            if ($match) {
                $match->update([
                    'source_line_id' => $line->id,
                    'amount_cents' => $line->amount_cents,
                    'expected_on' => $expectedOn?->toDateString(),
                    'account_id' => $line->account?->id,
                ]);
                $unlinked = $unlinked->reject(fn (PlanLine $existing) => (int) $existing->id === (int) $match->id)->values();
                $this->lastCopiedCount++;

                continue;
            }

            $created = $this->addPlanLine(
                $next,
                $line->category,
                $line->title,
                $line->amount_cents,
                $expectedOn,
                account: $line->account,
            );
            $created->update(['source_line_id' => $line->id]);
            $this->lastCopiedCount++;
        }

        $this->dropDuplicateTemplateShells($next);

        return $next;
    }

    private function dropDuplicateTemplateShells(BudgetPeriod $period): void
    {
        $kept = PlanLine::query()
            ->where('period_id', $period->id)
            ->whereNotNull('source_line_id')
            ->get(['category_id', 'title']);

        if ($kept->isEmpty()) {
            return;
        }

        PlanLine::query()
            ->where('period_id', $period->id)
            ->whereNull('source_line_id')
            ->whereNotNull('template_id')
            ->where('amount_cents', 0)
            ->orderBy('id')
            ->get()
            ->each(function (PlanLine $orphan) use ($kept): void {
                $duplicate = $kept->contains(
                    fn (PlanLine $row) => (int) $row->category_id === (int) $orphan->category_id
                        && $row->title === $orphan->title,
                );

                if ($duplicate) {
                    $orphan->delete();
                }
            });
    }

    public function periodBefore(BudgetPeriod $period): BudgetPeriod
    {
        $period->loadMissing('household');
        $end = CarbonImmutable::parse($period->starts_on)->subDay();
        $existing = $this->containing($period->household, $end);

        if ($existing) {
            return $existing;
        }

        $start = $this->calendar->boundsFor($end, $period->household->anchor_day)[0];
        $previous = BudgetPeriod::query()
            ->where('household_id', $period->household_id)
            ->whereDate('ends_on', '<', $period->starts_on->toDateString())
            ->orderByDesc('ends_on')
            ->first();
        $custom = false;

        if ($previous) {
            $afterPrevious = CarbonImmutable::parse($previous->ends_on)->addDay();

            if ($afterPrevious->toDateString() > $start->toDateString()) {
                $start = $afterPrevious;
                $custom = true;
            }
        }

        if ($start->toDateString() > $end->toDateString()) {
            throw new InvalidArgumentException(__('Нельзя построить предыдущий период: соседний период перекрывает дату.'));
        }

        return $this->createFromBounds($period->household, $start, $end, $custom);
    }

    public function updateBounds(BudgetPeriod $period, CarbonInterface $start, CarbonInterface $end): void
    {
        $start = CarbonImmutable::parse($start->toDateString());
        $end = CarbonImmutable::parse($end->toDateString());
        $this->assertCanOccupy($period, $start, $end);

        $period->update([
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'custom_bounds' => true,
        ]);
    }

    public function alignToAnchor(BudgetPeriod $period): void
    {
        $period->loadMissing('household');
        $household = $period->household;
        $today = CarbonImmutable::now($household->timezone)->toDateString();
        $reference = $period->starts_on->toDateString();

        if ($today >= $reference && $today <= $period->ends_on->toDateString()) {
            $reference = $today;
        }

        [$start, $end] = $this->calendar->boundsFor(CarbonImmutable::parse($reference), $household->anchor_day);
        $this->assertCanOccupy($period, $start, $end);

        $period->update([
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'custom_bounds' => false,
        ]);
    }

    public function useCalendarMonths(Household $household): void
    {
        $household->update(['anchor_day' => 1]);

        $periods = $household->periods()->orderBy('starts_on')->orderBy('id')->get();
        $assigned = [];

        foreach ($periods as $period) {
            $month = CarbonImmutable::parse($period->starts_on)->startOfMonth();
            $key = $month->format('Y-m');

            if (isset($assigned[$key])) {
                throw new InvalidArgumentException(__('Два периода попадают в один календарный месяц.'));
            }

            $assigned[$key] = [$period, $month, $month->endOfMonth()->startOfDay()];
        }

        foreach ($assigned as [$period, $start, $end]) {
            $period->update([
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'custom_bounds' => false,
            ]);
        }

        $this->rehomeByDate($household);
    }

    private function rehomeByDate(Household $household): void
    {
        $lines = PlanLine::query()
            ->whereIn('period_id', $household->periods()->select('id'))
            ->whereNotNull('expected_on')
            ->get();

        foreach ($lines as $line) {
            $home = $this->periodFor($household, CarbonImmutable::parse($line->expected_on));

            if ((int) $home->id !== (int) $line->period_id) {
                $line->update(['period_id' => $home->id]);
            }
        }

        foreach (Transaction::query()->where('household_id', $household->id)->get() as $transaction) {
            $home = $this->periodFor($household, CarbonImmutable::parse($transaction->occurred_on));

            if ((int) $home->id !== (int) $transaction->period_id) {
                $transaction->update(['period_id' => $home->id]);
            }
        }
    }

    public function record(
        Household $household,
        Category $category,
        CashflowType $type,
        int $cents,
        CarbonInterface $date,
        ?string $note,
        ?PlanLine $planLine,
        ?int $userId,
        ?Account $account = null,
    ): Transaction {
        if ($cents <= 0) {
            throw new InvalidArgumentException(__('Сумма должна быть больше нуля.'));
        }

        $period = $this->periodFor($household, $date);

        if ($planLine && (int) $planLine->period_id !== (int) $period->id) {
            $planLine = null;
        }

        $transaction = Transaction::create([
            'household_id' => $household->id,
            'period_id' => $period->id,
            'category_id' => $category->id,
            'plan_line_id' => $planLine?->id,
            'account_id' => $account?->id,
            'type' => $type,
            'amount_cents' => $cents,
            'occurred_on' => CarbonImmutable::parse($date)->toDateString(),
            'note' => $note,
            'created_by' => $userId,
        ]);

        $account?->apply($type, $cents);

        if ($planLine !== null) {
            app(DebtBook::class)->settle($planLine, $transaction);
        }

        return $transaction;
    }

    public function revise(
        Transaction $transaction,
        Category $category,
        int $cents,
        CarbonInterface $date,
        ?string $note,
        ?Account $account = null,
    ): Transaction {
        if ($cents <= 0) {
            throw new InvalidArgumentException(__('Сумма должна быть больше нуля.'));
        }

        if ((int) $category->household_id !== (int) $transaction->household_id || $category->type !== $transaction->type) {
            throw new InvalidArgumentException(__('Категория не подходит к этой операции.'));
        }

        $transaction->account?->apply($transaction->type, -$transaction->amount_cents);

        $period = $this->periodFor($transaction->household, $date);
        $planLineId = $transaction->plan_line_id;

        if ($planLineId) {
            $stillFits = PlanLine::query()
                ->whereKey($planLineId)
                ->where('period_id', $period->id)
                ->where('category_id', $category->id)
                ->exists();
            $planLineId = $stillFits ? $planLineId : null;
        }

        $transaction->update([
            'period_id' => $period->id,
            'category_id' => $category->id,
            'plan_line_id' => $planLineId,
            'account_id' => $account?->id,
            'amount_cents' => $cents,
            'occurred_on' => CarbonImmutable::parse($date)->toDateString(),
            'note' => $note,
        ]);

        $account?->apply($transaction->type, $cents);

        return $transaction->refresh();
    }

    public function moveFactToPlan(Transaction $transaction, CarbonImmutable $monthStart): PlanLine
    {
        if ($transaction->receipt_path) {
            throw new InvalidArgumentException(__('У операции есть чек. Это факт, его нельзя перенести в план.'));
        }

        $transaction->loadMissing('category', 'account', 'household');
        $category = $transaction->category;

        if ($category === null) {
            throw new InvalidArgumentException(__('У операции нет категории.'));
        }

        $period = $this->periodFor($transaction->household, $monthStart);
        $title = trim((string) $transaction->note);
        $title = $title !== '' ? $title : $category->name;
        $cents = (int) $transaction->amount_cents;
        $account = $transaction->account;

        $transaction->account?->apply($transaction->type, -$cents);
        $transaction->delete();

        return $this->addPlanLine($period, $category, $title, $cents, null, null, $account);
    }

    public function addPlanLine(BudgetPeriod $period, Category $category, string $title, int $cents, ?CarbonInterface $expectedOn = null, ?PlanTemplate $template = null, ?Account $account = null): PlanLine
    {
        if ($cents < 0) {
            throw new InvalidArgumentException(__('Сумма плана не может быть отрицательной.'));
        }

        return PlanLine::create([
            'period_id' => $period->id,
            'category_id' => $category->id,
            'account_id' => $account?->id,
            'template_id' => $template?->id,
            'title' => $title,
            'amount_cents' => $cents,
            'expected_on' => $expectedOn?->toDateString() ?? $this->calendar->expectedOn(
                $period->starts_on,
                $period->ends_on,
                $template?->expected_day,
            )?->toDateString(),
        ]);
    }

    /**
     * Plan line for recording a fact: reuse the category's line, or a parent-row
     * titled like this child (legacy «Питание / Продукты домой»), instead of a 0 € shell.
     */
    public function resolvePlanLine(BudgetPeriod $period, Category $category, ?Account $account = null): PlanLine
    {
        $this->absorbParentTitleLine($period, $category);

        $lines = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $category->id)
            ->orderByDesc('amount_cents')
            ->orderBy('id')
            ->get();

        $preferred = $lines->first();

        if ($preferred) {
            $this->dropEmptySiblingShells($period, $category, $preferred);

            return $preferred->refresh();
        }

        return $this->addPlanLine($period, $category, $category->name, 0, null, null, $account);
    }

    private function absorbParentTitleLine(BudgetPeriod $period, Category $category): void
    {
        if ($category->parent_id === null) {
            return;
        }

        $misplaced = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $category->parent_id)
            ->where('title', $category->name)
            ->orderByDesc('amount_cents')
            ->orderBy('id')
            ->get();

        if ($misplaced->isEmpty()) {
            return;
        }

        $keeper = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $category->id)
            ->orderByDesc('amount_cents')
            ->orderBy('id')
            ->first();

        foreach ($misplaced as $line) {
            if ($keeper === null) {
                $line->update(['category_id' => $category->id]);
                Transaction::query()
                    ->where('plan_line_id', $line->id)
                    ->update(['category_id' => $category->id]);
                $keeper = $line;

                continue;
            }

            $preferIncoming = (int) $line->amount_cents > (int) $keeper->amount_cents
                || ((int) $line->amount_cents === (int) $keeper->amount_cents
                    && (int) $line->id < (int) $keeper->id);

            if ($preferIncoming) {
                Transaction::query()
                    ->where('plan_line_id', $keeper->id)
                    ->update([
                        'plan_line_id' => $line->id,
                        'category_id' => $category->id,
                    ]);
                $line->update([
                    'category_id' => $category->id,
                    'account_id' => $line->account_id ?? $keeper->account_id,
                    'source_line_id' => $line->source_line_id ?? $keeper->source_line_id,
                    'template_id' => $line->template_id ?? $keeper->template_id,
                ]);
                Transaction::query()
                    ->where('plan_line_id', $line->id)
                    ->update(['category_id' => $category->id]);

                if (! $this->planLineHasEntries($keeper)) {
                    $keeper->delete();
                }

                $keeper = $line;

                continue;
            }

            Transaction::query()
                ->where('plan_line_id', $line->id)
                ->update([
                    'plan_line_id' => $keeper->id,
                    'category_id' => $category->id,
                ]);

            if ((int) $keeper->amount_cents === 0 && (int) $line->amount_cents > 0) {
                $keeper->update([
                    'amount_cents' => $line->amount_cents,
                    'expected_on' => $line->expected_on,
                    'account_id' => $keeper->account_id ?? $line->account_id,
                    'source_line_id' => $keeper->source_line_id ?? $line->source_line_id,
                    'template_id' => $keeper->template_id ?? $line->template_id,
                ]);
            }

            if (! $this->planLineHasEntries($line)) {
                $line->delete();
            }
        }
    }

    private function dropEmptySiblingShells(BudgetPeriod $period, Category $category, PlanLine $kept): void
    {
        PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $category->id)
            ->whereKeyNot($kept->id)
            ->where('amount_cents', 0)
            ->whereNull('template_id')
            ->orderBy('id')
            ->get()
            ->each(function (PlanLine $shell) use ($kept): void {
                if ($this->planLineHasEntries($shell)) {
                    Transaction::query()
                        ->where('plan_line_id', $shell->id)
                        ->update([
                            'plan_line_id' => $kept->id,
                            'category_id' => $kept->category_id,
                        ]);
                }

                if (! $this->planLineHasEntries($shell)) {
                    $shell->delete();
                }
            });
    }

    private function planLineHasEntries(PlanLine $line): bool
    {
        if (Transaction::query()->where('plan_line_id', $line->id)->exists()) {
            return true;
        }

        return DebtInstallment::query()->where('plan_line_id', $line->id)->exists();
    }

    private function fillGap(Household $household, CarbonImmutable $day): BudgetPeriod
    {
        $previous = $household->periods()
            ->whereDate('ends_on', '<', $day->toDateString())
            ->orderByDesc('ends_on')
            ->first();
        $next = $household->periods()
            ->whereDate('starts_on', '>', $day->toDateString())
            ->orderBy('starts_on')
            ->first();

        $start = $previous
            ? CarbonImmutable::parse($previous->ends_on)->addDay()
            : $this->calendar->boundsFor($day, $household->anchor_day)[0];
        $naturalEnd = $this->calendar->boundsFor($start, $household->anchor_day)[1];
        $end = $naturalEnd;
        $custom = false;

        if ($next && $next->starts_on->toDateString() <= $naturalEnd->toDateString()) {
            $end = CarbonImmutable::parse($next->starts_on)->subDay();
            $custom = true;
        }

        $period = $this->createFromBounds($household, $start, $end, $custom);

        if ($period->ends_on->toDateString() < $day->toDateString()) {
            return $this->periodFor($household, $day);
        }

        return $period;
    }

    private function dateInPeriod(?CarbonImmutable $date, BudgetPeriod $period): ?CarbonImmutable
    {
        if ($date === null) {
            return null;
        }

        $start = CarbonImmutable::parse($period->starts_on)->startOfDay();
        $end = CarbonImmutable::parse($period->ends_on)->startOfDay();

        if ($date->lt($start)) {
            return $start;
        }

        if ($date->gt($end)) {
            return $end;
        }

        return $date;
    }

    private function containing(Household $household, CarbonInterface $day): ?BudgetPeriod
    {
        return $household->periods()
            ->whereDate('starts_on', '<=', $day->toDateString())
            ->whereDate('ends_on', '>=', $day->toDateString())
            ->first();
    }

    private function createFromBounds(Household $household, CarbonInterface $start, CarbonInterface $end, bool $custom = false): BudgetPeriod
    {
        $period = BudgetPeriod::create([
            'household_id' => $household->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'opening_balance_cents' => 0,
            'opening_manual' => false,
            'custom_bounds' => $custom,
            'status' => 'open',
        ]);

        $this->copyTemplates($period);

        return $period;
    }

    public function placeTemplate(PlanTemplate $template, BudgetPeriod $from): void
    {
        $template->loadMissing('category');
        $periods = BudgetPeriod::query()
            ->where('household_id', $from->household_id)
            ->whereDate('starts_on', '>=', $from->starts_on->toDateString())
            ->orderBy('starts_on')
            ->get();

        foreach ($periods as $period) {
            $this->syncTemplateLine($template, $period);
        }
    }

    public function retireTemplate(PlanTemplate $template, BudgetPeriod $from): void
    {
        $template->update(['active' => false]);

        $futureIds = BudgetPeriod::query()
            ->where('household_id', $from->household_id)
            ->whereDate('starts_on', '>', $from->ends_on->toDateString())
            ->pluck('id');

        PlanLine::query()
            ->where('template_id', $template->id)
            ->whereIn('period_id', $futureIds)
            ->delete();
    }

    private function syncTemplateLine(PlanTemplate $template, BudgetPeriod $period): void
    {
        $template->loadMissing('category');
        $expected = $this->calendar->expectedOn(
            $period->starts_on,
            $period->ends_on,
            $template->expected_day,
        )?->toDateString();
        $existing = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('template_id', $template->id)
            ->orderBy('id')
            ->first();

        if ($existing) {
            $existing->update([
                'category_id' => $template->category_id,
                'title' => $template->title,
                'amount_cents' => $template->amount_cents,
                'expected_on' => $expected,
            ]);

            return;
        }

        $categoryAlreadyPlanned = PlanLine::query()
            ->where('period_id', $period->id)
            ->where('category_id', $template->category_id)
            ->exists();

        if ($categoryAlreadyPlanned) {
            return;
        }

        $this->addPlanLine(
            $period,
            $template->category,
            $template->title,
            $template->amount_cents,
            template: $template,
        );
    }

    private function copyTemplates(BudgetPeriod $period): void
    {
        $templates = PlanTemplate::query()
            ->where('household_id', $period->household_id)
            ->where('active', true)
            ->get();

        foreach ($templates as $template) {
            $this->syncTemplateLine($template, $period);
        }
    }

    private function assertCanOccupy(BudgetPeriod $period, CarbonInterface $start, CarbonInterface $end): void
    {
        if ($end->toDateString() < $start->toDateString()) {
            throw new InvalidArgumentException(__('Конец периода должен быть не раньше начала.'));
        }

        if ($this->calendar->inclusiveDays($start, $end) > 366) {
            throw new InvalidArgumentException(__('Период не может быть длиннее года.'));
        }

        $overlap = BudgetPeriod::query()
            ->where('household_id', $period->household_id)
            ->whereKeyNot($period->id)
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException(__('Эти даты пересекаются с другим периодом.'));
        }

        $outside = $period->transactions()
            ->where(function ($query) use ($start, $end) {
                $query->whereDate('occurred_on', '<', $start->toDateString())
                    ->orWhereDate('occurred_on', '>', $end->toDateString());
            })
            ->exists();

        if ($outside) {
            throw new InvalidArgumentException(__('В периоде есть операции за пределами новых дат.'));
        }
    }
}
