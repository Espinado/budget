<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Enums\InterestMode;
use App\Livewire\Budget\Debts;
use App\Livewire\Budget\DebtShow;
use App\Livewire\Budget\Dashboard;
use App\Enums\DebtKind;
use App\Models\Debt;
use App\Models\PlanLine;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Budget\DebtBook;
use App\Services\Budget\PeriodGenerator;
use App\Support\CreditCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_loan_schedule_replaces_one_plan_payment_and_does_not_copy_forward(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $book = app(DebtBook::class);
        $generator = app(PeriodGenerator::class);
        $november = $generator->periodFor($household, CarbonImmutable::parse('2026-11-01'));

        $this->assertSame('removed', $book->removeUnpaidPlanLine($household, '2026-11-01', 'Платеж по кредиту', 15_304));
        $this->assertSame(1, $november->planLines()->where('title', 'Платеж по кредиту')->count());

        $debt = $book->place($household, $credit, 'Inbank', 'L3722113191', '3722113191', 12_900, $this->inbank());

        $this->assertSame(70_517, $debt->remainingCents());
        $this->assertSame(15_403, (int) $november->planLines()->where('title', 'Inbank')->value('amount_cents'));
        $this->assertSame('2026-11-15', $november->planLines()->where('title', 'Inbank')->firstOrFail()->expected_on->toDateString());

        $march = $generator->periodFor($household, CarbonImmutable::parse('2027-03-15'));
        $february = $generator->periodFor($household, CarbonImmutable::parse('2027-02-15'));
        $this->assertSame(13_649, (int) $march->planLines()->where('title', 'Inbank')->value('amount_cents'));

        $generator->copyPlanOntoNext($february);

        $this->assertSame(1, $march->planLines()->where('title', 'Inbank')->count());
        $this->assertSame(13_649, (int) $march->planLines()->where('title', 'Inbank')->value('amount_cents'));

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSeeHtml('data-multi-open')
            ->assertSeeHtml('md:grid-cols-2')
            ->assertSee('Личные долги — с людьми. Кредиты — банк и лизинг. Нажмите контрагента, чтобы открыть обязательство.')
            ->assertSeeInOrder(['Личные', 'Дебиторские долги', 'Кредиторские долги', 'Кредиты', 'Дебиторские долги', 'Кредиторские долги', 'Inbank'])
            ->assertSee('705,17 €')
            ->assertSee('15.11.2026 · 154,03 €')
            ->assertSee('Кредитный калькулятор')
            ->assertSeeHtml('bg-sky-100')
            ->assertDontSee('Два столбца: дебиторские и кредиторские')
            ->assertDontSee('Нам должны')
            ->assertDontSee('Мы должны')
            ->assertDontSee('Уже выплачено месяцев')
            ->assertDontSee('141,30 €')
            ->assertDontSee('L3722113191');

        $this->actingAs($user)
            ->get(route('debts.show', $debt))
            ->assertOk()
            ->assertSee('L3722113191')
            ->assertSee('12,90%')
            ->assertSee('141,30 €')
            ->assertSee('15.11.2026')
            ->assertSee('Изменить условия')
            ->assertDontSee('Текущая ставка')
            ->assertDontSee('Сохранить платы')
            ->assertDontSee('Сохранить ставку')
            ->assertDontSee('value="12,90"', false);

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $november->id])
            ->test(Dashboard::class)
            ->assertDontSee('Кредитный калькулятор')
            ->assertDontSee('153,04 €')
            ->assertSee('Долги')
            ->assertSee('Дебиторские долги')
            ->assertSee('Кредиторские долги')
            ->assertSee('705,17 €')
            ->assertSee('text-rose-700">Кредиторские долги', false)
            ->call('toggleCategory', $credit->id)
            ->assertSee('Inbank')
            ->assertSee('15.11.2026');

        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));
        $line = $november->planLines()->where('title', 'Inbank')->firstOrFail();
        $before = (int) $cash->fresh()->balance_cents;

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $november->id])
            ->test(Dashboard::class)
            ->set('entryType', 'expense')
            ->set('entryAmount', '154,03')
            ->set('entryCategoryId', $credit->id)
            ->set('entryAccountId', $cash->id)
            ->set('entryDate', '2026-11-15')
            ->set('entryPlanLineId', $line->id)
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $payment = $november->transactions()->where('plan_line_id', $line->id)->firstOrFail();

        $this->assertSame($before - 15_403, (int) $cash->fresh()->balance_cents);
        $this->assertSame(56_387, Debt::query()->findOrFail($debt->id)->remainingCents());

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSee('563,87 €')
            ->assertSee('15.12.2026 · 154,03 €')
            ->assertDontSee('15.11.2026');

        $this->actingAs($user)
            ->get(route('debts.show', $debt))
            ->assertOk()
            ->assertSee('563,87 €')
            ->assertDontSee('оплачен')
            ->assertDontSee('15.11.2026')
            ->assertSee('15.12.2026 · 154,03 €');

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('deleteTransaction', $payment->id);

        $this->assertSame($before, (int) $cash->fresh()->balance_cents);
        $this->assertSame(70_517, Debt::query()->findOrFail($debt->id)->remainingCents());

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSee('705,17 €')
            ->assertSee('15.11.2026 · 154,03 €')
            ->assertDontSee('15.12.2026');
    }

    public function test_a_counterparty_rate_changes_from_the_nearest_unpaid_installment(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $book = app(DebtBook::class);
        $debt = $book->place($household, $credit, 'Inbank', 'L3722113191', '3722113191', 12_900, $this->inbank());
        $november = $debt->installments->firstWhere('sequence', 8);
        $december = $debt->installments->firstWhere('sequence', 9);

        $dates = $debt->installments->sortBy('sequence')->map(fn ($row) => $row->due_on->toDateString())->values()->all();

        $component = Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->assertSee('Изменить условия')
            ->assertDontSee('Новая ставка действует с ближайшего неоплаченного платежа')
            ->call('toggleConditions')
            ->assertSee('Новая ставка действует с ближайшего неоплаченного платежа')
            ->assertSee('data-focus-fields', false)
            ->assertSee('Дата окончания договора не меняется')
            ->assertSee('Новая плата входит со следующего месяца')
            ->assertSet('rateFixed', '12,90')
            ->assertSet('rateEuribor', '0,00')
            ->call('saveConditions')
            ->assertSet('editingConditions', false)
            ->assertSet('rateFixed', '12,90')
            ->assertSet('rateEuribor', '0,00');

        $this->assertSame(15_051, (int) $december->fresh()->total_cents);

        $component
            ->call('toggleConditions')
            ->set('rateFixed', '6')
            ->assertSee('С ближайшего платежа, 15.11.2026')
            ->assertSee('Дата окончания остаётся')
            ->call('saveConditions')
            ->assertSet('rateFixed', '6,00')
            ->assertSet('rateEuribor', '0,00')
            ->assertSee('6,00%')
            ->assertSee('148,06 €');

        $november->refresh();
        $december->refresh();
        $tailPrincipal = (int) $debt->installments()->where('sequence', '>=', 8)->sum('principal_cents');

        $this->assertSame(14_806, (int) $november->total_cents);
        $this->assertSame(13_963, (int) $november->principal_cents);
        $this->assertSame(56_554, (int) $december->principal_before_cents);
        $this->assertSame(490, (int) $december->fee_cents);
        $this->assertSame(14_806, (int) $december->total_cents);
        $this->assertSame((int) $december->total_cents, (int) $december->planLine()->value('amount_cents'));
        $this->assertSame(70_517, $tailPrincipal);
        $saved = $debt->fresh()->load('installments');
        $january = $saved->installments->firstWhere('sequence', 10);
        $this->assertSame(5, $saved->installments->count());
        $this->assertSame($dates, $saved->installments->sortBy('sequence')->map(fn ($row) => $row->due_on->toDateString())->values()->all());
        $this->assertSame('2027-03-15', $saved->installments->sortByDesc(fn ($row) => $row->due_on->toDateString())->first()->due_on->toDateString());
        $this->assertLessThan(15_403, (int) $december->total_cents);
        $this->assertSame((int) $december->total_cents, (int) $january->total_cents);
        $this->assertSame(6_000, (int) $saved->annual_rate_bps);
        $this->assertSame(6_000, (int) $saved->fixed_rate_bps);
        $this->assertSame(0, (int) $saved->euribor_rate_bps);
    }

    public function test_a_new_debt_can_be_a_friend_loan_or_a_loan_with_interest(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $periods = app(PeriodGenerator::class);
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $beforeDebit = (int) $debit->balance_cents;

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->assertSee('Новый долг')
            ->assertSee('← К бюджету')
            ->assertSeeHtml('bg-sky-100')
            ->assertSeeHtml('ring-sky-200')
            ->assertSee('Пока пусто')
            ->assertDontSeeHtml('text-stone-600 hover:text-ink')
            ->call('openNew')
            ->set('newKind', 'personal')
            ->set('newDirection', 'lend')
            ->set('newMode', 'lump')
            ->set('newInterest', 'none')
            ->set('newAmount', '100,00')
            ->set('newDate', '2026-11-15')
            ->set('newCounterparty', 'Анна')
            ->call('saveNew')
            ->assertHasErrors('newAccountId');

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->set('newKind', 'personal')
            ->set('newDirection', 'lend')
            ->set('newMode', 'lump')
            ->set('newAmount', '100,00')
            ->set('newDate', '2026-11-15')
            ->set('newCounterparty', 'Анна')
            ->set('newAccountId', $debit->id)
            ->call('saveNew')
            ->assertHasNoErrors()
            ->assertSee('Личные')
            ->assertSee('Дебиторские долги')
            ->assertSee('Анна')
            ->assertSee('15.11.2026 · 100,00 €');

        $this->assertSame($beforeDebit - 10_000, (int) $debit->fresh()->balance_cents);
        $issue = Transaction::query()->where('household_id', $household->id)->where('note', 'Анна')->where('type', CashflowType::Expense)->firstOrFail();
        $this->assertSame(10_000, (int) $issue->amount_cents);
        $this->assertSame($debit->id, (int) $issue->account_id);
        $this->assertSame('Личные долги', $issue->category->name);
        $this->assertSame(CashflowType::Expense, $issue->category->type);

        $november = $periods->periodFor($household, CarbonImmutable::parse('2026-11-15'));
        $anna = $november->planLines()->where('title', 'Анна')->firstOrFail();
        $this->assertSame(10_000, (int) $anna->amount_cents);
        $this->assertNull($anna->account_id);
        $this->assertSame(CashflowType::Income, $anna->category->type);
        $this->assertSame('Личные долги', $anna->category->name);
        $this->assertNull($anna->category->parent_id);
        $annaDebt = Debt::query()->where('counterparty', 'Анна')->firstOrFail();
        $this->assertSame($issue->id, (int) $annaDebt->issued_transaction_id);
        $this->assertSame(DebtKind::Personal, $annaDebt->kind);

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->set('newKind', 'personal')
            ->set('newDirection', 'lend')
            ->set('newMode', 'parts')
            ->set('newAmount', '100,00')
            ->set('newMonths', '3')
            ->set('newCounterparty', 'Борис')
            ->set('newAccountId', $debit->id)
            ->call('saveNew')
            ->assertHasNoErrors()
            ->assertSee('дата не указана · 33,33 €');

        $this->assertSame(0, $household->periods()->whereHas('planLines', fn ($query) => $query->where('title', 'Борис'))->count());
        $this->assertSame(2, Debt::query()->whereNull('contract_number')->count());
        $this->assertSame($beforeDebit - 20_000, (int) $debit->fresh()->balance_cents);

        $incomeBefore = (int) Transaction::query()->where('household_id', $household->id)->where('type', CashflowType::Income)->sum('amount_cents');

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newMode', 'lump')
            ->set('newInterest', 'fixed')
            ->set('newFixed', '12')
            ->set('newAmount', '100,00')
            ->set('newDate', '2026-11-24')
            ->set('newCounterparty', 'Марк')
            ->set('newContract', 'Д-1')
            ->set('newReference', 'REF-1')
            ->call('saveNew')
            ->assertHasNoErrors()
            ->assertSee('101,00 €');

        $this->assertSame($incomeBefore, (int) Transaction::query()->where('household_id', $household->id)->where('type', CashflowType::Income)->sum('amount_cents'));
        $mark = Debt::query()->where('counterparty', 'Марк')->firstOrFail();
        $this->assertSame('Д-1', $mark->contract_number);
        $this->assertSame('REF-1', $mark->reference);
        $this->assertSame(DebtKind::Bank, $mark->kind);
        $this->assertSame(12_000, (int) $mark->annual_rate_bps);
        $this->assertSame(100, (int) $mark->installments()->value('interest_cents'));
        $markLine = $november->planLines()->where('title', 'Марк')->firstOrFail();
        $this->assertSame(10_100, (int) $markLine->amount_cents);
        $this->assertSame(CashflowType::Expense, $markLine->category->type);
        $this->assertSame('Кредитные обязательства', $markLine->category->name);
        $this->assertNotSame(
            $household->categories()->where('name', 'Кредит')->value('id'),
            $markLine->category_id,
        );

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newMode', 'parts')
            ->set('newInterest', 'mixed')
            ->set('newFixed', '10')
            ->set('newEuribor', '2')
            ->set('newAmount', '100,00')
            ->set('newMonths', '2')
            ->set('newDate', '2026-11-15')
            ->set('newCounterparty', 'Банк')
            ->call('saveNew')
            ->assertHasNoErrors();

        $bank = Debt::query()->where('counterparty', 'Банк')->firstOrFail();
        $installments = $bank->installments()->orderBy('sequence')->get();
        $this->assertSame(10_000, (int) $bank->fixed_rate_bps);
        $this->assertSame(2_000, (int) $bank->euribor_rate_bps);
        $this->assertSame(5_075, (int) $installments[0]->total_cents);
        $this->assertSame(5_075, (int) $installments[1]->total_cents);
        $this->assertSame(4_975, (int) $installments[0]->principal_cents);
        $this->assertSame(5_025, (int) $installments[1]->principal_cents);
        $december = $periods->periodFor($household, CarbonImmutable::parse('2026-12-15'));
        $this->assertSame(5_075, (int) $november->planLines()->where('title', 'Банк')->value('amount_cents'));
        $this->assertSame(5_075, (int) $december->planLines()->where('title', 'Банк')->value('amount_cents'));

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->set('newKind', 'personal')
            ->set('newDirection', 'lend')
            ->set('newAmount', '50,00')
            ->set('newCounterparty', 'Ольга')
            ->set('newAccountId', $debit->id)
            ->set('newDate', '2026-09-01')
            ->call('saveNew')
            ->assertHasErrors('newDate');

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newMode', 'lump')
            ->set('newInterest', 'fixed')
            ->set('newFixed', '5')
            ->set('newAmount', '50,00')
            ->set('newCounterparty', 'Ольга')
            ->call('saveNew')
            ->assertHasErrors('newDate');

        $this->assertNull(Debt::query()->where('counterparty', 'Ольга')->first());
    }

    public function test_personal_borrow_is_not_income_and_repayment_is_expense(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $before = (int) $debit->balance_cents;
        $incomeBefore = (int) Transaction::query()->where('household_id', $household->id)->where('type', CashflowType::Income)->sum('amount_cents');
        $period = app(PeriodGenerator::class)->periodFor($household, CarbonImmutable::parse('2026-10-25'));
        $incomeFiguresBefore = app(\App\Services\Budget\PeriodSummary::class)->figures($period)->actualIncome;

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->set('newKind', 'personal')
            ->set('newDirection', 'borrow')
            ->set('newMode', 'lump')
            ->set('newAmount', '40,00')
            ->set('newDate', '2026-11-20')
            ->set('newCounterparty', 'Сосед')
            ->call('saveNew')
            ->assertHasErrors('newAccountId');

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->set('newKind', 'personal')
            ->set('newDirection', 'borrow')
            ->set('newMode', 'lump')
            ->set('newAmount', '40,00')
            ->set('newDate', '2026-11-20')
            ->set('newCounterparty', 'Сосед')
            ->set('newAccountId', $debit->id)
            ->call('saveNew')
            ->assertHasNoErrors();

        $this->assertSame($before + 4_000, (int) $debit->fresh()->balance_cents);
        $this->assertSame($incomeBefore, (int) Transaction::query()->where('household_id', $household->id)->where('type', CashflowType::Income)->sum('amount_cents'));
        $this->assertSame($incomeFiguresBefore, app(\App\Services\Budget\PeriodSummary::class)->figures($period->fresh())->actualIncome);

        $debt = Debt::query()->where('counterparty', 'Сосед')->firstOrFail();
        $this->assertSame(DebtKind::Personal, $debt->kind);
        $this->assertSame($debit->id, (int) $debt->account_id);
        $issue = Transaction::query()->findOrFail($debt->issued_transaction_id);
        $this->assertSame(CashflowType::LiabilityIn, $issue->type);
        $this->assertSame(4_000, (int) $issue->amount_cents);
        $this->assertSame($debit->id, (int) $issue->account_id);
        $this->assertStringContainsString('взяли в долг', (string) $issue->note);

        $line = $debt->installments()->firstOrFail()->planLine;
        $this->assertSame(CashflowType::Expense, $line->category->type);
        $this->assertSame('Личные долги', $line->category->name);

        $line->update(['account_id' => $debit->id]);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'Europe/Riga'));

        Livewire::actingAs($user)
            ->withQueryParams(['period' => $line->period_id])
            ->test(Dashboard::class)
            ->call('payLine', $line->id)
            ->assertSet('payingLineId', $line->id)
            ->assertHasNoErrors()
            ->set('payAccountId', $debit->id)
            ->call('payLine', $line->id)
            ->assertHasNoErrors();

        $payment = $line->transactions()->firstOrFail();
        $this->assertSame(CashflowType::Expense, $payment->type);
        $this->assertSame(4_000, (int) $payment->amount_cents);
        $this->assertSame($before, (int) $debit->fresh()->balance_cents);
        $this->assertSame($incomeBefore, (int) Transaction::query()->where('household_id', $household->id)->where('type', CashflowType::Income)->sum('amount_cents'));
    }

    public function test_a_revolving_debt_stops_at_six_months_and_shrinks_after_an_extra_payment(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $book = app(DebtBook::class);
        $from = CarbonImmutable::parse('2026-10-25');
        $rows = $book->draft(88_062, 'parts', 24, CarbonImmutable::parse('2026-11-12'), 38_400, $from);

        $this->assertCount(24, $rows);
        $this->assertSame(5_312, $rows[0]['total_cents']);
        $this->assertSame(2_818, $rows[0]['interest_cents']);
        $this->assertSame(88_062, array_sum(array_column($rows, 'principal_cents')));

        $debt = $book->place($household, $credit, 'Creditea', null, null, 38_400, $rows, DebtDirection::Borrow, 38_400, 0, 'revolving');

        $this->assertSame(24, $debt->installments()->count());
        $this->assertSame(6, $debt->installments()->whereNotNull('plan_line_id')->count());
        $latest = $debt->installments()->whereNotNull('plan_line_id')->get()->sortByDesc(fn ($row) => $row->due_on->toDateString())->first();
        $this->assertSame('2027-04-12', $latest->due_on->toDateString());
        $this->assertNull($household->periods()->whereDate('starts_on', '2027-05-01')->first());

        $screen = Livewire::actingAs($user)->test(Dashboard::class);
        for ($step = 0; $step < 6; $step++) {
            $screen->call('next');
        }
        $screen->assertSet('periodId', $household->periods()->whereDate('starts_on', '2027-04-01')->value('id'));
        $screen->call('next')->assertSet('periodId', $household->periods()->whereDate('starts_on', '2027-04-01')->value('id'));
        $this->assertNull($household->periods()->whereDate('starts_on', '2027-05-01')->first());

        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'Europe/Riga'));
        $small = $book->draft(10_000, 'parts', 3, CarbonImmutable::parse('2026-11-12'), 0, $from);
        $friend = $book->place($household, $credit, 'Линия', null, null, 0, $small, DebtDirection::Borrow, 0, 0, 'revolving');
        $line = $friend->installments()->where('sequence', 1)->firstOrFail()->planLine;
        $generator = app(PeriodGenerator::class);
        $beforeCash = (int) $cash->fresh()->balance_cents;
        $payment = $generator->record($household, $credit, CashflowType::Expense, 5_000, CarbonImmutable::parse('2026-11-12'), null, $line, $user->id, $cash);
        $second = $friend->installments()->where('sequence', 2)->firstOrFail();
        $third = $friend->installments()->where('sequence', 3)->firstOrFail();

        $this->assertSame($beforeCash - 5_000, (int) $cash->fresh()->balance_cents);
        $this->assertSame(1, Transaction::query()->where('account_id', $cash->id)->where('amount_cents', 5_000)->count());
        $this->assertSame(5_000, (int) $second->principal_before_cents);
        $this->assertSame(3_333, (int) $second->total_cents);
        $this->assertSame(1_667, (int) $third->total_cents);

        Livewire::actingAs($user)->test(Dashboard::class)->call('deleteTransaction', $payment->id);
        $second->refresh();
        $third->refresh();
        $this->assertSame(6_667, (int) $second->principal_before_cents);
        $this->assertSame(3_333, (int) $second->total_cents);
        $this->assertSame(3_334, (int) $third->total_cents);
        $this->assertSame($beforeCash, (int) $cash->fresh()->balance_cents);
    }

    public function test_optional_fees_join_unpaid_payments_and_the_schedule_scrolls(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $periods = app(PeriodGenerator::class);

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newMode', 'parts')
            ->set('newInterest', 'none')
            ->set('newAmount', '100,00')
            ->set('newMonths', '2')
            ->set('newDate', '2026-11-15')
            ->set('newCounterparty', 'Лизинг')
            ->set('newMonthlyFee', '4,90')
            ->set('newIssuanceFee', '10,00')
            ->call('saveNew')
            ->assertHasNoErrors()
            ->assertSee('64,90 €');

        $debt = Debt::query()->where('counterparty', 'Лизинг')->firstOrFail();
        $rows = $debt->installments()->orderBy('sequence')->get();
        $this->assertSame(490, (int) $debt->monthly_fee_cents);
        $this->assertSame(1_000, (int) $debt->issuance_fee_cents);
        $this->assertSame(6_490, (int) $rows[0]->total_cents);
        $this->assertSame(1_000, (int) $rows[0]->other_cents);
        $this->assertSame(490, (int) $rows[0]->fee_cents);
        $this->assertSame(5_490, (int) $rows[1]->total_cents);
        $this->assertSame(0, (int) $rows[1]->other_cents);
        $this->assertSame(490, (int) $rows[1]->fee_cents);

        $november = $periods->periodFor($household, CarbonImmutable::parse('2026-11-15'));
        $december = $periods->periodFor($household, CarbonImmutable::parse('2026-12-15'));
        $this->assertSame(6_490, (int) $november->planLines()->where('title', 'Лизинг')->value('amount_cents'));
        $this->assertSame(5_490, (int) $december->planLines()->where('title', 'Лизинг')->value('amount_cents'));

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->assertSee('max-height: 18rem', false)
            ->assertSee('Изменить условия')
            ->call('toggleConditions')
            ->assertSet('feeMonthly', '4,90')
            ->assertSet('feeIssuance', '10,00')
            ->set('feeMonthly', '1,00')
            ->set('feeIssuance', '')
            ->call('saveConditions')
            ->assertSet('editingConditions', false)
            ->assertHasNoErrors();

        $rows[0]->refresh();
        $rows[1]->refresh();
        $debt->refresh();
        $this->assertSame(100, (int) $debt->monthly_fee_cents);
        $this->assertSame(0, (int) $debt->issuance_fee_cents);
        $this->assertSame(6_490, (int) $rows[0]->total_cents);
        $this->assertSame(490, (int) $rows[0]->fee_cents);
        $this->assertSame(1_000, (int) $rows[0]->other_cents);
        $this->assertSame(5_100, (int) $rows[1]->total_cents);
        $this->assertSame(100, (int) $rows[1]->fee_cents);
        $this->assertSame(6_490, (int) $november->planLines()->where('title', 'Лизинг')->value('amount_cents'));
        $this->assertSame(5_100, (int) $december->planLines()->where('title', 'Лизинг')->value('amount_cents'));

        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $line = $rows[0]->planLine;
        app(PeriodGenerator::class)->record($household, $line->category, CashflowType::Expense, 6_490, CarbonImmutable::parse('2026-11-15'), null, $line, $user->id, $cash);
        app(DebtBook::class)->changeFees($debt->fresh(), 200, 0);

        $rows[0]->refresh();
        $rows[1]->refresh();
        $this->assertSame(200, (int) $debt->fresh()->monthly_fee_cents);
        $this->assertSame(6_490, (int) $rows[0]->total_cents);
        $this->assertSame(5_100, (int) $rows[1]->total_cents);
        $this->assertSame(6_490, (int) $november->planLines()->where('title', 'Лизинг')->value('amount_cents'));
        $this->assertSame(5_100, (int) $december->planLines()->where('title', 'Лизинг')->value('amount_cents'));

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newAmount', '20,00')
            ->set('newCounterparty', 'Сбор')
            ->set('newMonthlyFee', 'abc')
            ->call('saveNew')
            ->assertHasErrors('newMonthlyFee');
    }

    public function test_an_smscredit_minimum_starts_at_the_invoice_and_shrinks_after_an_extra_payment(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $book = app(DebtBook::class);
        $rows = $book->minimumInstallments(54_750, 43_800, CarbonImmutable::parse('2026-10-23'), CarbonImmutable::parse('2026-11-27'));

        $this->assertSame(3_943, $rows[0]['total_cents']);
        $this->assertSame(2_300, $rows[0]['interest_cents']);
        $this->assertSame(1_643, $rows[0]['principal_cents']);
        $this->assertSame(3_505, $rows[1]['total_cents']);
        $this->assertLessThan($rows[0]['total_cents'], $rows[1]['total_cents']);
        $this->assertSame(54_750, array_sum(array_column($rows, 'principal_cents')));
        $this->assertGreaterThan(12, count($rows));
        $this->assertSame($rows[array_key_last($rows)]['principal_before_cents'], $rows[array_key_last($rows)]['principal_cents']);

        $debt = $book->place($household, $credit, 'SMScredit', null, null, 43_800, $rows, DebtDirection::Borrow, 43_800, 0, 'minimum', CarbonImmutable::parse('2026-10-23'));

        $this->assertSame('minimum', $debt->schedule);
        $this->assertSame(count($rows), $debt->installments()->count());
        $this->assertSame(6, $debt->installments()->whereNotNull('plan_line_id')->count());
        $this->assertSame(3_943, (int) $debt->installments()->where('sequence', 1)->firstOrFail()->planLine->amount_cents);
        $this->assertNull($household->periods()->whereDate('starts_on', '2027-05-01')->first());

        $this->travelTo(CarbonImmutable::parse('2026-11-27 12:00:00', 'Europe/Riga'));
        $line = $debt->installments()->where('sequence', 1)->firstOrFail()->planLine;
        $kept = $debt->installments()->where('sequence', 2)->firstOrFail();
        $keptTotal = (int) $kept->total_cents;
        $keptInterest = (int) $kept->interest_cents;
        $beforeCash = (int) $cash->fresh()->balance_cents;
        $payment = app(PeriodGenerator::class)->record($household, $credit, CashflowType::Expense, 13_943, CarbonImmutable::parse('2026-11-27'), null, $line, $user->id, $cash);
        $second = $debt->installments()->where('sequence', 2)->firstOrFail();
        $positive = $debt->installments()->where('total_cents', '>', 0)->count();

        $this->assertSame($beforeCash - 13_943, (int) $cash->fresh()->balance_cents);
        $this->assertSame(1, Transaction::query()->where('account_id', $cash->id)->where('amount_cents', 13_943)->count());
        $this->assertSame(43_107, (int) $second->principal_before_cents);
        $this->assertSame($keptInterest, (int) $second->interest_cents);
        $this->assertSame($keptTotal, (int) $second->total_cents);
        $this->assertLessThan(count($rows), $positive);

        Livewire::actingAs($user)->test(Dashboard::class)->call('deleteTransaction', $payment->id);
        $second->refresh();
        $this->assertSame(53_107, (int) $second->principal_before_cents);
        $this->assertSame(3_505, (int) $second->total_cents);
        $this->assertSame(count($rows), $debt->installments()->where('total_cents', '>', 0)->count());
    }

    public function test_a_second_fact_on_a_fixed_schedule_drops_later_months_and_keeps_the_payment(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $book = app(DebtBook::class);
        $rows = $book->draft(10_000, 'parts', 3, CarbonImmutable::parse('2026-11-15'), 0, CarbonImmutable::parse('2026-11-15'));
        $debt = $book->place($household, $credit, 'Inbank', 'extra-term', null, 0, $rows);
        $line = $debt->installments()->where('sequence', 1)->firstOrFail()->planLine;
        $planned = (int) $line->amount_cents;
        $beforeCash = (int) $cash->fresh()->balance_cents;
        $periods = app(PeriodGenerator::class);
        $periods->record($household, $credit, CashflowType::Expense, $planned, CarbonImmutable::parse('2026-11-15'), null, $line, $user->id, $cash);
        $extra = $periods->record($household, $credit, CashflowType::Expense, 3_334, CarbonImmutable::parse('2026-11-15'), null, $line, $user->id, $cash);
        $second = $debt->installments()->where('sequence', 2)->firstOrFail();
        $third = $debt->installments()->where('sequence', 3)->firstOrFail();

        $this->assertSame($beforeCash - $planned - 3_334, (int) $cash->fresh()->balance_cents);
        $this->assertSame(1, Transaction::query()->where('account_id', $cash->id)->where('amount_cents', 3_334)->count());
        $this->assertSame($planned, (int) $second->total_cents);
        $this->assertSame($planned, (int) $second->planLine->amount_cents);
        $this->assertSame(3_333, (int) $second->principal_before_cents);
        $this->assertSame(0, (int) $third->total_cents);
        $this->assertSame(3_333, $debt->fresh()->remainingCents());
        $this->assertSame(2, $debt->installments()->where('total_cents', '>', 0)->count());

        Livewire::actingAs($user)->test(Dashboard::class)->call('deleteTransaction', $extra->id);
        $second->refresh();
        $third->refresh();
        $this->assertSame(6_667, (int) $second->principal_before_cents);
        $this->assertSame(3_334, (int) $third->total_cents);
        $this->assertSame($beforeCash - $planned, (int) $cash->fresh()->balance_cents);
    }

    public function test_a_running_mortgage_keeps_the_statement_rate_to_three_decimals(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newOrigin', 'running')
            ->set('newProduct', 'loan')
            ->set('newInterest', 'mixed')
            ->set('newAmount', '37 243,29')
            ->set('newDate', '2026-11-10')
            ->set('newEnd', '2042-11-12')
            ->set('newFixed', '2,38')
            ->set('newEuribor', '2,477')
            ->set('newTenor', '3')
            ->set('newCounterparty', 'Ипотека')
            ->set('newContract', '2104/17H21')
            ->assertSee('278,43')
            ->assertSee('Сверьте платёж с банком.')
            ->assertSee('193 платежа')
            ->call('saveNew')
            ->assertHasNoErrors();

        $debt = Debt::query()->where('contract_number', '2104/17H21')->firstOrFail();
        $first = $debt->installments()->orderBy('sequence')->firstOrFail();
        $last = $debt->installments()->reorder()->orderByDesc('due_on')->firstOrFail();

        $this->assertSame(193, $debt->installments()->count());
        $this->assertSame(2_380, (int) $debt->fixed_rate_bps);
        $this->assertSame(2_477, (int) $debt->euribor_rate_bps);
        $this->assertSame(4_857, (int) $debt->annual_rate_bps);
        $this->assertSame(3, (int) $debt->euribor_tenor_months);
        $this->assertSame('2026-11-10', $debt->rate_reset_on->toDateString());
        $this->assertSame(27_843, (int) $first->total_cents);
        $this->assertSame(15_074, (int) $first->interest_cents);
        $this->assertSame(12_769, (int) $first->principal_cents);
        $this->assertSame('2042-11-10', $last->due_on->toDateString());
        $this->assertSame(6, $debt->installments()->whereNotNull('plan_line_id')->count());
    }

    public function test_a_mortgage_rate_change_keeps_the_end_date_and_can_change_the_payment(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newOrigin', 'running')
            ->set('newProduct', 'loan')
            ->set('newInterest', 'mixed')
            ->set('newAmount', '37 243,29')
            ->set('newDate', '2026-11-10')
            ->set('newEnd', '2042-11-12')
            ->set('newFixed', '2,38')
            ->set('newEuribor', '2,477')
            ->set('newTenor', '3')
            ->set('newCounterparty', 'Ипотека')
            ->set('newContract', '2104/17H21')
            ->call('saveNew')
            ->assertHasNoErrors();

        $debt = Debt::query()->where('contract_number', '2104/17H21')->firstOrFail();
        $dates = $debt->installments->sortBy('sequence')->map(fn ($row) => $row->due_on->toDateString())->values()->all();
        $secondBefore = (int) $debt->installments->firstWhere('sequence', 2)->total_cents;

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->assertSee('10.11.2026')
            ->assertSee('278,43')
            ->assertSee('10.11.2042')
            ->call('toggleConditions')
            ->set('rateEuribor', '4,500')
            ->assertSee('С ближайшего платежа, 10.11.2026')
            ->call('saveConditions')
            ->assertHasNoErrors()
            ->assertSee('319,53')
            ->assertSee('10.11.2042');

        $debt->refresh()->load('installments');
        $first = $debt->installments->firstWhere('sequence', 1);
        $second = $debt->installments->firstWhere('sequence', 2);
        $last = $debt->installments->sortByDesc(fn ($row) => $row->due_on->toDateString())->first();

        $this->assertSame(193, $debt->installments->count());
        $this->assertSame($dates, $debt->installments->sortBy('sequence')->map(fn ($row) => $row->due_on->toDateString())->values()->all());
        $this->assertSame('2042-11-10', $last->due_on->toDateString());
        $this->assertSame(31_953, (int) $first->total_cents);
        $this->assertSame(21_353, (int) $first->interest_cents);
        $this->assertSame(10_600, (int) $first->principal_cents);
        $this->assertSame(31_953, (int) $second->total_cents);
        $this->assertSame((int) $second->total_cents, (int) $debt->installments->firstWhere('sequence', 3)->total_cents);
        $this->assertGreaterThan(0, (int) $last->total_cents);
        $this->assertSame(3_724_329, (int) $debt->installments->sum('principal_cents'));

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->set('rateEuribor', '1,000')
            ->call('saveConditions')
            ->assertHasNoErrors();

        $debt->refresh()->load('installments');
        $first = $debt->installments->firstWhere('sequence', 1);
        $second = $debt->installments->firstWhere('sequence', 2);
        $last = $debt->installments->sortByDesc(fn ($row) => $row->due_on->toDateString())->first();

        $this->assertSame(193, $debt->installments->count());
        $this->assertSame('2042-11-10', $last->due_on->toDateString());
        $this->assertSame(25_042, (int) $first->total_cents);
        $this->assertLessThan($secondBefore, (int) $second->total_cents);
        $this->assertSame(3_724_329, (int) $debt->installments->sum('principal_cents'));
    }

    public function test_luminor_euribor_reprices_the_nearest_payment_to_the_bank_schedule(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newOrigin', 'running')
            ->set('newProduct', 'loan')
            ->set('newInterest', 'mixed')
            ->set('newAmount', '37 115,60')
            ->set('newDate', '2026-12-10')
            ->set('newEnd', '2042-11-12')
            ->set('newFixed', '2,38')
            ->set('newEuribor', '2,477')
            ->set('newTenor', '3')
            ->set('newCounterparty', 'Luminor')
            ->set('newContract', '2104/17H21')
            ->call('saveNew')
            ->assertHasNoErrors();

        $debt = Debt::query()->where('contract_number', '2104/17H21')->firstOrFail();
        $this->assertSame(192, $debt->installments()->count());
        $this->assertNotSame(28_463, (int) $debt->installments()->orderBy('sequence')->firstOrFail()->total_cents);

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->call('toggleConditions')
            ->set('rateEuribor', '2,794')
            ->assertSee('С ближайшего платежа, 10.12.2026')
            ->assertSee('284,63')
            ->call('saveConditions')
            ->assertHasNoErrors();

        $debt->refresh()->load('installments');
        $rows = $debt->installments->sortBy('sequence')->values();
        $december = $rows[0];
        $january = $rows[1];
        $february = $rows[2];
        $last = $rows->last();

        $this->assertSame(5_174, (int) $debt->annual_rate_bps);
        $this->assertSame(2_380, (int) $debt->fixed_rate_bps);
        $this->assertSame(2_794, (int) $debt->euribor_rate_bps);
        $this->assertSame(192, $rows->count());
        $this->assertSame('2042-11-10', $last->due_on->toDateString());
        $this->assertSame('2026-12-10', $december->due_on->toDateString());
        $this->assertSame(3_711_560, (int) $december->principal_before_cents);
        $this->assertSame(12_460, (int) $december->principal_cents);
        $this->assertSame(16_003, (int) $december->interest_cents);
        $this->assertSame(28_463, (int) $december->total_cents);
        $this->assertSame('2027-01-10', $january->due_on->toDateString());
        $this->assertSame(3_699_100, (int) $january->principal_before_cents);
        $this->assertSame(12_514, (int) $january->principal_cents);
        $this->assertSame(15_949, (int) $january->interest_cents);
        $this->assertSame(28_463, (int) $january->total_cents);
        $this->assertSame(3_686_586, (int) $february->principal_before_cents);
        $this->assertSame(12_568, (int) $february->principal_cents);
        $this->assertSame(15_895, (int) $february->interest_cents);
        $this->assertSame(28_463, (int) $february->total_cents);
    }

    public function test_saving_the_same_euribor_rebuilds_a_stale_december_row(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Debts::class)
            ->call('openNew')
            ->call('chooseBank')
            ->set('newOrigin', 'running')
            ->set('newProduct', 'loan')
            ->set('newInterest', 'mixed')
            ->set('newAmount', '37 115,60')
            ->set('newDate', '2026-12-10')
            ->set('newEnd', '2042-11-12')
            ->set('newFixed', '2,38')
            ->set('newEuribor', '2,477')
            ->set('newTenor', '3')
            ->set('newCounterparty', 'Luminor')
            ->set('newContract', '2104/17H21-stale')
            ->call('saveNew')
            ->assertHasNoErrors();

        $debt = Debt::query()->where('contract_number', '2104/17H21-stale')->firstOrFail();
        $stale = $debt->installments()->orderBy('sequence')->firstOrFail();
        $this->assertSame(3_711_560, (int) $stale->principal_before_cents);
        $this->assertSame(12_820, (int) $stale->principal_cents);
        $this->assertSame(15_023, (int) $stale->interest_cents);
        $this->assertSame(27_843, (int) $stale->total_cents);

        $debt->update([
            'annual_rate_bps' => 5_174,
            'fixed_rate_bps' => 2_380,
            'euribor_rate_bps' => 2_794,
        ]);

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt->fresh()])
            ->call('toggleConditions')
            ->assertSet('rateFixed', '2,38')
            ->assertSet('rateEuribor', '2,794')
            ->call('saveConditions')
            ->assertHasNoErrors()
            ->assertSee('284,63')
            ->assertSee('График пересчитан с ближайшего платежа');

        $debt->refresh()->load('installments');
        $rows = $debt->installments->sortBy('sequence')->values();
        $december = $rows[0];
        $january = $rows[1];

        $this->assertSame(5_174, (int) $debt->annual_rate_bps);
        $this->assertSame(2_380, (int) $debt->fixed_rate_bps);
        $this->assertSame(2_794, (int) $debt->euribor_rate_bps);
        $this->assertSame('2026-12-10', $december->due_on->toDateString());
        $this->assertSame(3_711_560, (int) $december->principal_before_cents);
        $this->assertSame(12_460, (int) $december->principal_cents);
        $this->assertSame(16_003, (int) $december->interest_cents);
        $this->assertSame(28_463, (int) $december->total_cents);
        $this->assertSame('2027-01-10', $january->due_on->toDateString());
        $this->assertSame(3_699_100, (int) $january->principal_before_cents);
        $this->assertSame(12_514, (int) $january->principal_cents);
        $this->assertSame(15_949, (int) $january->interest_cents);
        $this->assertSame(28_463, (int) $january->total_cents);
    }

    public function test_a_paid_installment_is_not_rewritten_when_the_rate_changes(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $book = app(DebtBook::class);
        $debt = $book->place($household, $credit, 'Inbank', 'L3722113191', '3722113191', 12_900, $this->inbank());
        $november = $debt->installments->firstWhere('sequence', 8);
        $line = $november->planLine;
        $payment = app(PeriodGenerator::class)->record($household, $credit, CashflowType::Expense, 15_403, CarbonImmutable::parse('2026-11-15'), null, $line, $user->id, $cash);

        $this->assertSame($payment->id, (int) $november->fresh()->paid_transaction_id);

        $book->changeRate($debt->fresh(), 20_000, 20_000, 0);

        $debt->refresh()->load('installments');
        $november = $debt->installments->firstWhere('sequence', 8);
        $december = $debt->installments->firstWhere('sequence', 9);
        $january = $debt->installments->firstWhere('sequence', 10);
        $last = $debt->installments->sortByDesc(fn ($row) => $row->due_on->toDateString())->first();

        $calculator = new CreditCalculator();
        $february = $debt->installments->firstWhere('sequence', 11);
        $raised = $calculator->annuity(56_387, 4, 20_000) + 490;
        $this->assertSame($payment->id, (int) $november->paid_transaction_id);
        $this->assertSame(15_403, (int) $november->total_cents);
        $this->assertSame(14_130, (int) $november->principal_cents);
        $this->assertSame($raised, (int) $december->total_cents);
        $this->assertSame($raised, (int) $january->total_cents);
        $this->assertSame($raised, (int) $february->total_cents);
        $this->assertGreaterThan($calculator->annuity(56_387, 4, 12_900) + 490, (int) $january->total_cents);
        $this->assertSame(5, $debt->installments->count());
        $this->assertSame('2027-03-15', $last->due_on->toDateString());
        $this->assertSame(70_517, (int) $debt->installments->sum('principal_cents'));
    }

    public function test_a_lease_buyout_stays_when_the_rate_changes(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $book = app(DebtBook::class);
        $from = CarbonImmutable::parse('2026-10-25');
        $rows = $book->draft(100_000, 'parts', 4, CarbonImmutable::parse('2026-11-10'), 6_000, $from, 0, 0, 20_000);
        $debt = $book->place($household, $credit, 'Лизинг', 'L-1', null, 6_000, $rows, DebtDirection::Borrow, 6_000, 0, 'fixed', null, 20_000);
        $before = $debt->installments->sortBy('sequence')->values();
        $secondBefore = (int) $before[1]->total_cents;

        $book->changeRate($debt, 12_000, 12_000, 0);

        $debt->refresh()->load('installments');
        $after = $debt->installments->sortBy('sequence')->values();
        $last = $after->last();

        $this->assertCount($before->count(), $after);
        $this->assertSame(
            $before->map(fn ($row) => $row->due_on->toDateString())->all(),
            $after->map(fn ($row) => $row->due_on->toDateString())->all(),
        );
        $calculator = new CreditCalculator;
        $this->assertSame($calculator->interest(100_000, 12_000), (int) $after[0]->interest_cents);
        $this->assertSame(
            $calculator->annuity(80_000, 4, 12_000) + $calculator->interest(20_000, 12_000),
            (int) $after[0]->total_cents,
        );
        $this->assertSame(20_702, (int) $after[0]->total_cents);
        $this->assertGreaterThan($secondBefore, (int) $after[1]->total_cents);
        $this->assertSame(20_000, (int) $last->principal_cents);
        $this->assertSame(0, (int) $last->interest_cents);
        $this->assertSame(100_000, (int) $after->sum('principal_cents'));
        $this->assertSame('2027-03-10', $last->due_on->toDateString());
    }

    public function test_unchanged_conditions_do_not_rebuild_the_schedule(): void
    {
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $debt = app(DebtBook::class)->place($household, $credit, 'Inbank', 'L3722113191', '3722113191', 12_900, $this->inbank());
        $stamps = $debt->installments->mapWithKeys(fn ($row) => [$row->id => $row->updated_at?->toDateTimeString()])->all();
        $debtStamp = $debt->updated_at?->toDateTimeString();

        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:05:00', 'Europe/Riga'));

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->assertSet('editingConditions', false)
            ->assertSee('Изменить условия')
            ->assertDontSee('Сохранить')
            ->call('toggleConditions')
            ->assertSee('Сохранить')
            ->assertSee('Отмена')
            ->set('rateFixed', '12.90')
            ->set('rateEuribor', '0.00')
            ->set('feeMonthly', '4.90')
            ->set('feeIssuance', '0')
            ->call('saveConditions')
            ->assertHasNoErrors()
            ->assertSet('editingConditions', false)
            ->assertSee('График пересчитан с ближайшего платежа')
            ->assertDontSee('Ставка сохранена')
            ->assertDontSee('Платы сохранены')
            ->call('toggleConditions')
            ->set('rateFixed', '20')
            ->set('feeMonthly', '9,00')
            ->call('cancelConditions')
            ->assertSet('editingConditions', false)
            ->assertSet('rateFixed', '12,90')
            ->assertSet('feeMonthly', '4,90')
            ->call('toggleConditions')
            ->set('rateEuribor', '3')
            ->call('toggleConditions')
            ->assertSet('editingConditions', false)
            ->assertSet('rateEuribor', '0,00');

        $debt->refresh()->load('installments');
        $this->assertSame($debtStamp, $debt->updated_at?->toDateTimeString());
        $this->assertSame(12_900, (int) $debt->annual_rate_bps);
        $this->assertSame(12_900, (int) $debt->fixed_rate_bps);
        $this->assertSame(0, (int) $debt->euribor_rate_bps);
        $this->assertSame(490, (int) $debt->monthly_fee_cents);
        $this->assertSame(0, (int) $debt->issuance_fee_cents);
        $this->assertNotSame($stamps, $debt->installments->mapWithKeys(fn ($row) => [$row->id => $row->updated_at?->toDateTimeString()])->all());
        $repriced = $debt->installments->firstWhere('sequence', 8);
        $this->assertSame(13_803, (int) $repriced->principal_cents);
        $this->assertSame(758, (int) $repriced->interest_cents);
        $this->assertSame(490, (int) $repriced->fee_cents);

        $november = $debt->installments->firstWhere('sequence', 8);
        $december = $debt->installments->firstWhere('sequence', 9);

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->call('toggleConditions')
            ->set('rateFixed', '12.90')
            ->set('rateEuribor', '0,00')
            ->set('feeMonthly', '1,00')
            ->set('feeIssuance', '')
            ->call('saveConditions')
            ->assertHasNoErrors()
            ->assertSet('editingConditions', false)
            ->assertSee('Платы сохранены');

        $november->refresh();
        $december->refresh();
        $debt->refresh();
        $this->assertSame(12_900, (int) $debt->annual_rate_bps);
        $this->assertSame(100, (int) $debt->monthly_fee_cents);
        $this->assertSame(13_803, (int) $november->principal_cents);
        $this->assertSame(758, (int) $november->interest_cents);
        $this->assertSame(490, (int) $november->fee_cents);
        $this->assertSame(15_051, (int) $november->total_cents);
        $this->assertSame(13_951, (int) $december->principal_cents);
        $this->assertSame(610, (int) $december->interest_cents);
        $this->assertSame(100, (int) $december->fee_cents);

        Livewire::actingAs($user)
            ->test(DebtShow::class, ['debt' => $debt])
            ->call('toggleConditions')
            ->set('rateFixed', '6')
            ->set('feeMonthly', '2,00')
            ->call('saveConditions')
            ->assertHasNoErrors()
            ->assertSet('editingConditions', false)
            ->assertSee('Ставка сохранена')
            ->assertSee('Платы сохранены');

        $november->refresh();
        $december->refresh();
        $debt->refresh()->load('installments');
        $last = $debt->installments->sortByDesc(fn ($row) => $row->due_on->toDateString())->first();
        $this->assertSame(6_000, (int) $debt->annual_rate_bps);
        $this->assertSame(200, (int) $debt->monthly_fee_cents);
        $this->assertSame(13_963, (int) $november->principal_cents);
        $this->assertSame(353, (int) $november->interest_cents);
        $this->assertSame(490, (int) $november->fee_cents);
        $this->assertSame(14_806, (int) $november->total_cents);
        $this->assertSame(200, (int) $december->fee_cents);
        $this->assertNotSame(606, (int) $december->interest_cents);
        $this->assertSame(5, $debt->installments->count());
        $this->assertSame('2027-03-15', $last->due_on->toDateString());
    }

    public function test_paying_a_credit_line_records_the_full_installment(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $food = $household->categories()->where('name', 'Питание')->firstOrFail();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $line = PlanLine::query()->where('category_id', $credit->id)->where('amount_cents', 15_304)->firstOrFail();
        $open = PlanLine::query()->where('category_id', $credit->id)->where('amount_cents', 3_000)->firstOrFail();
        $groceries = PlanLine::query()->where('category_id', $food->id)->where('amount_cents', 0)->firstOrFail();
        $line->update(['account_id' => $debit->id]);

        $debt = app(DebtBook::class)->place($household, $credit, 'Банк', 'PAY-1', null, 0, [[
            'sequence' => 1,
            'due_on' => '2026-11-20',
            'principal_before_cents' => 5_000,
            'principal_cents' => 5_000,
            'interest_cents' => 0,
            'other_cents' => 0,
            'fee_cents' => 0,
            'total_cents' => 5_000,
        ]]);
        $installment = $debt->installments()->firstOrFail();
        $installment->planLine->update(['account_id' => $debit->id]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->assertSee('Оплатить')
            ->assertSee('Ипотечный кредит')
            ->call('toggleCategory', $credit->id)
            ->assertSee('wire:click="payLine('.$line->id.')"', false)
            ->assertDontSee('со счёта Luminor Debet', false)
            ->assertDontSee('wire:click="payLine('.$groceries->id.')"', false)
            ->call('payLine', $open->id)
            ->assertSet('payingLineId', $open->id)
            ->assertSee('Выберите счёт')
            ->call('payLine', $open->id)
            ->assertHasErrors('pay.'.$open->id)
            ->call('payLine', $line->id)
            ->set('payAccountId', $debit->id)
            ->assertSee('Оплатить «'.$line->title.'» полностью, '.money(15_304).' со счёта Luminor Debet?', false)
            ->call('payLine', $line->id)
            ->assertHasNoErrors()
            ->assertDontSee('wire:click="payLine('.$line->id.')"', false)
            ->call('payLine', $installment->plan_line_id)
            ->set('payAccountId', $debit->id)
            ->call('payLine', $installment->plan_line_id)
            ->assertHasNoErrors();

        $paid = $line->transactions()->firstOrFail();
        $installmentPayment = $installment->planLine->transactions()->firstOrFail();
        $this->assertSame(15_304, (int) $paid->amount_cents);
        $this->assertSame($debit->id, (int) $paid->account_id);
        $this->assertSame('2026-11-11', $paid->occurred_on->toDateString());
        $this->assertSame('2026-11-20', $installmentPayment->occurred_on->toDateString());
        $this->assertSame(16_829 - 15_304 - 5_000, (int) $debit->fresh()->balance_cents);
        $this->assertSame(0, $open->transactions()->count());
        $this->assertNotNull($installment->fresh()->paid_transaction_id);
        $this->assertSame(5_000, (int) $installment->planLine->transactions()->value('amount_cents'));
    }

    public function test_paying_without_a_checking_account_waits_and_a_future_due_date_debits_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $generator = app(PeriodGenerator::class);
        $generator->record($household, $credit, CashflowType::Expense, 100, CarbonImmutable::parse('2026-11-01'), null, null, $user->id, $debit);
        $before = (int) $debit->fresh()->balance_cents;

        $debt = app(DebtBook::class)->place($household, $credit, 'Creditea', null, null, 0, [
            [
                'sequence' => 1,
                'due_on' => '2026-11-12',
                'principal_before_cents' => 88_062,
                'principal_cents' => 2_494,
                'interest_cents' => 2_818,
                'other_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => 5_312,
            ],
            [
                'sequence' => 2,
                'due_on' => '2026-12-12',
                'principal_before_cents' => 85_568,
                'principal_cents' => 2_574,
                'interest_cents' => 2_738,
                'other_cents' => 0,
                'fee_cents' => 0,
                'total_cents' => 5_312,
            ],
        ]);
        $line = $debt->installments()->where('sequence', 1)->firstOrFail()->planLine;
        $this->assertNull($line->account_id);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('payLine', $line->id)
            ->assertSet('payingLineId', $line->id)
            ->assertSet('payAccountId', null)
            ->call('payLine', $line->id)
            ->assertHasErrors('pay.'.$line->id);

        $this->assertSame(0, $line->transactions()->count());
        $this->assertNull($debt->installments()->where('sequence', 1)->value('paid_transaction_id'));
        $this->assertSame($before, (int) $debit->fresh()->balance_cents);

        $line->update(['account_id' => $debit->id]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('payLine', $line->id)
            ->assertSet('payAccountId', null)
            ->set('payAccountId', $debit->id)
            ->call('payLine', $line->id)
            ->assertHasNoErrors();

        $payment = $line->transactions()->firstOrFail();
        $this->assertSame(CashflowType::Expense, $payment->type);
        $this->assertSame(5_312, (int) $payment->amount_cents);
        $this->assertSame($debit->id, (int) $payment->account_id);
        $this->assertSame('2026-11-12', $payment->occurred_on->toDateString());
        $this->assertSame($before - 5_312, (int) $debit->fresh()->balance_cents);
        $this->assertSame($payment->id, (int) $debt->installments()->where('sequence', 1)->value('paid_transaction_id'));

        $period = $generator->periodFor($household, CarbonImmutable::parse('2026-11-11', 'Europe/Riga'));

        $this->actingAs($user)
            ->get(route('dashboard', ['period' => $period->id]))
            ->assertOk()
            ->assertSee('114,17')
            ->assertDontSee('167,29');

        $this->actingAs($user)
            ->get(route('debts.show', $debt))
            ->assertOk()
            ->assertDontSee('оплачен')
            ->assertDontSee('12.11.2026')
            ->assertSee('12.12.2026');
    }

    public function test_a_schedule_payment_asks_for_a_checking_account_before_it_debits(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'Europe/Riga'));
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $credit = $household->categories()->where('name', 'Кредит')->firstOrFail();
        $debit = $household->accounts()->where('name', 'Luminor Debet')->firstOrFail();
        $piggy = $household->accounts()->create([
            'name' => 'Копилка теста',
            'kind' => 'bank',
            'role' => AccountRole::Savings,
            'balance_cents' => 9_000,
            'sort' => 90,
        ]);
        $debt = app(DebtBook::class)->place($household, $credit, 'Inbank', 'SCHED-PAY', null, 12_900, $this->inbank());
        $line = $debt->installments()->where('sequence', 8)->firstOrFail()->planLine;
        $before = (int) $debit->fresh()->balance_cents;

        $this->assertSame(InterestMode::Schedule, $debt->fresh()->interest_mode);
        $this->assertSame(15_403, (int) $line->amount_cents);
        $this->assertNull($line->account_id);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('payLine', $line->id)
            ->assertSet('payingLineId', $line->id)
            ->assertSet('payAccountId', null)
            ->set('payAccountId', $piggy->id)
            ->call('payLine', $line->id)
            ->assertHasErrors(['pay.'.$line->id])
            ->set('payAccountId', $debit->id)
            ->call('payLine', $line->id)
            ->assertHasNoErrors();

        $payment = $line->transactions()->sole();
        $this->assertSame(CashflowType::Expense, $payment->type);
        $this->assertSame(15_403, (int) $payment->amount_cents);
        $this->assertSame($debit->id, (int) $payment->account_id);
        $this->assertSame('2026-11-15', $payment->occurred_on->toDateString());
        $this->assertSame($before - 15_403, (int) $debit->fresh()->balance_cents);
        $this->assertSame(9_000, (int) $piggy->fresh()->balance_cents);
        $this->assertSame($payment->id, (int) $debt->installments()->where('sequence', 8)->value('paid_transaction_id'));
        $this->assertSame(1, Transaction::query()->where('account_id', $debit->id)->where('amount_cents', 15_403)->count());
    }

    /**
     * @return list<array{sequence: int, due_on: string, principal_before_cents: int, principal_cents: int, interest_cents: int, other_cents: int, fee_cents: int, total_cents: int}>
     */
    private function inbank(): array
    {
        return [
            ['sequence' => 8, 'due_on' => '2026-11-15', 'principal_before_cents' => 70_517, 'principal_cents' => 14_130, 'interest_cents' => 783, 'other_cents' => 0, 'fee_cents' => 490, 'total_cents' => 15_403],
            ['sequence' => 9, 'due_on' => '2026-12-15', 'principal_before_cents' => 56_387, 'principal_cents' => 14_307, 'interest_cents' => 606, 'other_cents' => 0, 'fee_cents' => 490, 'total_cents' => 15_403],
            ['sequence' => 10, 'due_on' => '2027-01-15', 'principal_before_cents' => 42_080, 'principal_cents' => 14_446, 'interest_cents' => 467, 'other_cents' => 0, 'fee_cents' => 490, 'total_cents' => 15_403],
            ['sequence' => 11, 'due_on' => '2027-02-15', 'principal_before_cents' => 27_634, 'principal_cents' => 14_606, 'interest_cents' => 307, 'other_cents' => 0, 'fee_cents' => 490, 'total_cents' => 15_403],
            ['sequence' => 12, 'due_on' => '2027-03-15', 'principal_before_cents' => 13_028, 'principal_cents' => 13_028, 'interest_cents' => 131, 'other_cents' => 0, 'fee_cents' => 490, 'total_cents' => 13_649],
        ];
    }
}
