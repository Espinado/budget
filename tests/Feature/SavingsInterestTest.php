<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Livewire\Budget\Settings;
use App\Models\Account;
use App\Models\AccountInterestEntry;
use App\Models\Household;
use App\Models\User;
use App\Services\Budget\SavingsInterest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class SavingsInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_savings_account_can_store_and_edit_annual_rate(): void
    {
        [$user, $household] = $this->family();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('openNewAccount')
            ->set('accountName', 'Вклад')
            ->set('accountRole', AccountRole::Savings->value)
            ->set('accountAnnualRate', '3,5')
            ->call('addAccount')
            ->assertHasNoErrors();

        $account = $household->accounts()->where('name', 'Вклад')->firstOrFail();
        $this->assertSame(AccountRole::Savings, $account->role);
        $this->assertSame(3_500, (int) $account->annual_rate_bps);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('editAccount', $account->id)
            ->assertSet('editAccountAnnualRate', '3,50')
            ->set('editAccountAnnualRate', '5')
            ->call('saveAccount')
            ->assertHasNoErrors();

        $this->assertSame(5_000, (int) $account->fresh()->annual_rate_bps);
    }

    public function test_operating_account_ignores_rate(): void
    {
        [$user, $household] = $this->family();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('openNewAccount')
            ->set('accountName', 'Карта')
            ->set('accountRole', AccountRole::Operating->value)
            ->set('accountAnnualRate', '10')
            ->call('addAccount')
            ->assertHasNoErrors();

        $account = $household->accounts()->where('name', 'Карта')->firstOrFail();
        $this->assertSame(0, (int) $account->annual_rate_bps);
    }

    public function test_daily_compound_interest_adds_correct_cents(): void
    {
        [, $household] = $this->family();
        $account = $household->accounts()->create([
            'name' => 'Копилка',
            'kind' => 'bank',
            'role' => AccountRole::Savings,
            'balance_cents' => 1_000_000,
            'annual_rate_bps' => 5_000,
            'sort' => 40,
        ]);

        $day = CarbonImmutable::parse('2026-10-27', 'Europe/Riga')->startOfDay();
        $added = app(SavingsInterest::class)->accrueAccount($account, $day);

        // round(1_000_000 × 5000 / 36_500_000) = 137
        $this->assertSame(137, $added);
        $this->assertSame(1_000_137, (int) $account->fresh()->balance_cents);
        $this->assertSame('2026-10-27', $account->fresh()->last_interest_on?->toDateString());

        $entry = AccountInterestEntry::query()->where('account_id', $account->id)->firstOrFail();
        $this->assertSame(137, (int) $entry->amount_cents);
        $this->assertSame(1_000_000, (int) $entry->balance_before_cents);
        $this->assertSame('2026-10-27', $entry->accrued_on->toDateString());
    }

    public function test_zero_rate_does_nothing(): void
    {
        [, $household] = $this->family();
        $account = $household->accounts()->create([
            'name' => 'Копилка',
            'kind' => 'bank',
            'role' => AccountRole::Savings,
            'balance_cents' => 1_000_000,
            'annual_rate_bps' => 0,
            'sort' => 40,
        ]);

        $added = app(SavingsInterest::class)->accrueAccount(
            $account,
            CarbonImmutable::parse('2026-10-27', 'Europe/Riga')->startOfDay(),
        );

        $this->assertSame(0, $added);
        $this->assertSame(1_000_000, (int) $account->fresh()->balance_cents);
        $this->assertNull($account->fresh()->last_interest_on);
        $this->assertSame(0, AccountInterestEntry::query()->count());
    }

    public function test_same_day_is_not_accrued_twice(): void
    {
        [, $household] = $this->family();
        $account = $household->accounts()->create([
            'name' => 'Копилка',
            'kind' => 'bank',
            'role' => AccountRole::Savings,
            'balance_cents' => 1_000_000,
            'annual_rate_bps' => 5_000,
            'sort' => 40,
        ]);

        $day = CarbonImmutable::parse('2026-10-27', 'Europe/Riga')->startOfDay();
        $service = app(SavingsInterest::class);

        $this->assertSame(137, $service->accrueAccount($account, $day));
        $this->assertSame(0, $service->accrueAccount($account->fresh(), $day));
        $this->assertSame(1_000_137, (int) $account->fresh()->balance_cents);
        $this->assertSame(1, AccountInterestEntry::query()->count());

        $this->travelTo($day->setTimezone('UTC')->addHours(12));
        Artisan::call('savings:accrue-interest');
        $this->assertSame(1_000_137, (int) $account->fresh()->balance_cents);
        $this->assertSame(1, AccountInterestEntry::query()->count());
    }

    public function test_compound_interest_uses_updated_balance_next_day(): void
    {
        [, $household] = $this->family();
        $account = $household->accounts()->create([
            'name' => 'Копилка',
            'kind' => 'bank',
            'role' => AccountRole::Savings,
            'balance_cents' => 1_000_000,
            'annual_rate_bps' => 5_000,
            'sort' => 40,
        ]);

        $service = app(SavingsInterest::class);
        $first = CarbonImmutable::parse('2026-10-27', 'Europe/Riga')->startOfDay();
        $second = $first->addDay();

        $this->assertSame(137, $service->accrueAccount($account, $first));
        // round(1_000_137 × 5000 / 36_500_000) = 137
        $this->assertSame(137, $service->accrueAccount($account->fresh(), $second));
        $this->assertSame(1_000_274, (int) $account->fresh()->balance_cents);
        $this->assertSame(2, AccountInterestEntry::query()->count());
    }

    /**
     * @return array{0: User, 1: Household}
     */
    private function family(): array
    {
        $user = User::factory()->create();
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);

        return [$user, $household];
    }
}
