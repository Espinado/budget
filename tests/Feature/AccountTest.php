<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Livewire\Budget\OpeningBalances;
use App\Livewire\Budget\Settings;
use App\Models\Account;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_balances_can_be_entered_after_registration(): void
    {
        $this->post('/register', [
            'name' => 'Анна',
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $user = User::query()->where('email', 'anna@example.com')->firstOrFail();
        $cash = $user->currentHousehold()->accounts()->where('name', 'Наличные деньги')->firstOrFail();
        $credit = $user->currentHousehold()->accounts()->where('name', 'Кредитная карта')->firstOrFail();
        $piggy = $user->currentHousehold()->accounts()->where('name', 'Копилка')->firstOrFail();
        $this->assertSame(AccountRole::Operating, $cash->role);
        $this->assertSame(AccountRole::Savings, $piggy->role);

        Livewire::actingAs($user)
            ->test(OpeningBalances::class)
            ->set('accountAmounts.'.$cash->id, '150,00')
            ->set('accountAmounts.'.$credit->id, '20.50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertSame(15_000, $cash->fresh()->balance_cents);
        $this->assertSame(2_050, $credit->fresh()->balance_cents);
        $this->assertFalse(session()->has('show_opening_balances'));
        $this->get('/budget')->assertOk()->assertSee('id="install-banner"', false);
    }

    public function test_accounts_can_be_renamed_and_a_balance_blocks_deletion(): void
    {
        $user = User::factory()->create();
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);

        foreach (Account::defaults() as $account) {
            $household->accounts()->create([
                ...$account,
                'balance_cents' => 0,
            ]);
        }

        $cash = $household->accounts()->where('name', 'Наличные деньги')->firstOrFail();
        $cash->update(['balance_cents' => 500]);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->assertSee('Расчётный счёт')
            ->assertSee('Накопительный счёт')
            ->assertSet('showNewAccount', false)
            ->assertSet('openAccountGroup', null)
            ->call('toggleAccountGroup', AccountRole::Operating->value)
            ->assertSee('Наличные деньги')
            ->assertDontSee('deleteAccount('.$cash->id.')', false)
            ->call('deleteAccount', $cash->id)
            ->assertSee('Счёт с остатком удалить нельзя.')
            ->call('openNewAccount')
            ->assertSet('showNewAccount', true)
            ->set('accountName', '')
            ->call('addAccount')
            ->assertHasErrors('accountName')
            ->assertSet('showNewAccount', true)
            ->set('accountName', 'Заначка')
            ->set('accountRole', AccountRole::Savings->value)
            ->call('addAccount')
            ->assertHasNoErrors()
            ->assertSet('showNewAccount', false)
            ->call('toggleAccountGroup', AccountRole::Savings->value)
            ->assertSet('openAccountGroup', AccountRole::Savings->value)
            ->assertSee('Заначка')
            ->call('editAccount', $cash->id)
            ->assertSet('editingAccountId', $cash->id)
            ->assertSet('openAccountGroup', AccountRole::Operating->value)
            ->assertSet('editAccountBalance', '5,00')
            ->set('editAccountName', 'Кошелёк')
            ->set('editAccountBalance', '12,50')
            ->call('saveAccount')
            ->assertSet('editingAccountId', null)
            ->assertSee('Кошелёк');

        $this->assertSame(1_250, (int) $cash->fresh()->balance_cents);

        $wallet = $household->accounts()->where('name', 'Заначка')->firstOrFail();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('deleteAccount', $wallet->id);

        $this->assertNull($wallet->fresh());
        $this->assertNotNull($cash->fresh());
        $this->assertSame('Кошелёк', $cash->fresh()->name);
        $this->assertSame(AccountRole::Operating, $cash->fresh()->role);
        $this->assertSame(AccountRole::Savings, $wallet->role);
    }

    public function test_account_balance_can_be_changed_while_editing(): void
    {
        $user = User::factory()->create();
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);

        $piggy = $household->accounts()->create([
            ...Account::piggyBank(),
            'balance_cents' => 17_274,
        ]);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editAccount', $piggy->id)
            ->assertSet('editAccountBalance', '172,74')
            ->set('editAccountBalance', '200')
            ->set('editAccountAnnualRate', '2,5')
            ->call('saveAccount')
            ->assertHasNoErrors();

        $piggy->refresh();
        $this->assertSame(20_000, (int) $piggy->balance_cents);
        $this->assertSame(2_500, (int) $piggy->annual_rate_bps);
    }
}
