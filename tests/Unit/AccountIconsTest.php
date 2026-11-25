<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Support\AccountIcons;
use PHPUnit\Framework\TestCase;

class AccountIconsTest extends TestCase
{
    public function test_wallets_get_a_matching_icon(): void
    {
        $cash = new Account(['name' => 'Наличные', 'kind' => 'cash', 'role' => 'operating']);
        $debit = new Account(['name' => 'Luminor Debet', 'kind' => 'bank', 'role' => 'operating']);
        $credit = new Account(['name' => 'Luminor Credit', 'kind' => 'bank', 'role' => 'operating']);
        $piggy = new Account(['name' => 'Копилка', 'kind' => 'bank', 'role' => 'savings']);

        $this->assertSame('coins', AccountIcons::mark($cash)['icon']);
        $this->assertSame('card', AccountIcons::mark($debit)['icon']);
        $this->assertSame('bg-sky-100 text-sky-800', AccountIcons::mark($debit)['tone']);
        $this->assertSame('card', AccountIcons::mark($credit)['icon']);
        $this->assertSame('bg-amber-100 text-amber-800', AccountIcons::mark($credit)['tone']);
        $this->assertSame('piggy', AccountIcons::mark($piggy)['icon']);
    }
}
