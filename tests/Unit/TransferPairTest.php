<?php

namespace Tests\Unit;

use App\Enums\AccountRole;
use App\Models\Account;
use Tests\TestCase;

class TransferPairTest extends TestCase
{
    public function test_named_debit_and_credit_are_the_transfer_defaults(): void
    {
        $cash = $this->account(1, 'Наличные', AccountRole::Operating);
        $debit = $this->account(2, 'Luminor Debet', AccountRole::Operating);
        $credit = $this->account(3, 'Luminor Credit', AccountRole::Operating);
        $piggy = $this->account(4, 'Копилка', AccountRole::Savings);

        $this->assertSame([2, 3], Account::transferPair(collect([$piggy, $cash, $credit, $debit])));
    }

    public function test_two_checking_accounts_are_used_when_debit_and_credit_are_not_named(): void
    {
        $piggy = $this->account(4, 'Копилка', AccountRole::Savings);
        $cash = $this->account(1, 'Наличные', AccountRole::Operating);
        $bank = $this->account(2, 'Банк', AccountRole::Operating);

        $this->assertSame([1, 2], Account::transferPair(collect([$piggy, $cash, $bank])));
    }

    private function account(int $id, string $name, AccountRole $role): Account
    {
        $account = new Account(['name' => $name, 'role' => $role]);
        $account->id = $id;

        return $account;
    }
}
