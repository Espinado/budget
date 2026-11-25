<?php

namespace App\Livewire\Budget;

use App\Enums\AccountRole;
use App\Enums\DebtDirection;
use App\Enums\DebtKind;
use App\Livewire\Budget\Concerns\RecordsDebt;
use App\Livewire\Concerns\NormalizesMoneyInput;
use App\Livewire\Concerns\PresentsDayFirstDates;
use App\Models\Debt;
use App\Models\Household;
use App\Services\Budget\Solvency;
use Livewire\Attributes\On;
use Livewire\Component;

class Debts extends Component
{
    use NormalizesMoneyInput;
    use PresentsDayFirstDates;
    use RecordsDebt;

    #[On('family-pulse')]
    public function refreshFromFamily(): void {}

    /**
     * @return list<string>
     */
    protected function dayFirstDateFields(): array
    {
        return ['newDate', 'newEnd', 'newReset'];
    }

    public function render()
    {
        $this->presentDayFirstDates();
        $debts = Debt::query()
            ->where('household_id', $this->household()->id)
            ->with('installments')
            ->orderBy('counterparty')
            ->orderBy('id')
            ->get();

        $personal = $debts->filter(fn (Debt $debt): bool => $debt->kind === DebtKind::Personal)->values();
        $bank = $debts->filter(fn (Debt $debt): bool => $debt->kind === DebtKind::Bank)->values();

        return view('livewire.budget.debts', [
            'personalOwed' => $personal->filter(fn (Debt $debt): bool => $debt->direction === DebtDirection::Borrow)->values(),
            'personalOwing' => $personal->filter(fn (Debt $debt): bool => $debt->direction === DebtDirection::Lend)->values(),
            'bankOwed' => $bank->filter(fn (Debt $debt): bool => $debt->direction === DebtDirection::Borrow)->values(),
            'bankOwing' => $bank->filter(fn (Debt $debt): bool => $debt->direction === DebtDirection::Lend)->values(),
            'operatingAccounts' => $this->household()->accounts()
                ->where('role', AccountRole::Operating)
                ->orderBy('sort')
                ->orderBy('id')
                ->get(),
            'preview' => $this->showNew ? $this->preview() : null,
            'solvency' => app(Solvency::class)->assess($this->household()),
        ])->title(__('Долги — Family Money'));
    }

    private function household(): Household
    {
        return auth()->user()->currentHousehold();
    }
}
