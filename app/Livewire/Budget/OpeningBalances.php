<?php

namespace App\Livewire\Budget;

use App\Livewire\Concerns\NormalizesMoneyInput;
use App\Models\Account;
use App\Support\Money;
use Livewire\Component;

class OpeningBalances extends Component
{
    use NormalizesMoneyInput;

    /** @var array<int, string> */
    public array $accountAmounts = [];

    public function mount(): void
    {
        if (! session('show_opening_balances')) {
            $this->redirect(route('dashboard'));

            return;
        }

        $accounts = auth()->user()->currentHousehold()->accounts()->orderBy('sort')->get();

        foreach ($accounts as $account) {
            $this->accountAmounts[$account->id] = '';
        }
    }

    public function save(): void
    {
        if (! session('show_opening_balances')) {
            $this->redirect(route('dashboard'));

            return;
        }

        $household = auth()->user()->currentHousehold();
        $updates = [];

        foreach ($this->accountAmounts as $id => $raw) {
            $account = Account::query()->where('household_id', $household->id)->find($id);

            if ($account === null) {
                continue;
            }

            $raw = trim((string) $raw);
            $cents = $raw === '' ? 0 : Money::toCents($raw);

            if ($cents === null || $cents < 0) {
                $this->addError('accountAmounts.'.$id, __('Укажите сумму в евро, ноль или больше.'));

                return;
            }

            $updates[] = [$account, $cents];
        }

        foreach ($updates as [$account, $cents]) {
            $account->update(['balance_cents' => $cents]);
        }

        session()->forget('show_opening_balances');
        session()->flash('status', __('Начальные остатки записаны.'));

        $this->redirect(route('dashboard'));
    }

    public function skip(): void
    {
        session()->forget('show_opening_balances');

        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.budget.opening-balances', [
            'accounts' => auth()->user()->currentHousehold()->accounts()->orderBy('sort')->get(),
        ])->title(__('Начальные остатки — Family Money'));
    }
}
