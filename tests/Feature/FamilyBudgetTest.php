<?php

namespace Tests\Feature;

use App\Enums\CashflowType;
use App\Livewire\Budget\Dashboard;
use App\Livewire\Budget\Settings;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdActivity;
use App\Models\User;
use App\Notifications\FamilyInviteNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoBudgetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class FamilyBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_spouse_sees_the_same_budget_and_the_others_action(): void
    {
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();
        $spouse = User::factory()->create([
            'name' => 'Анна',
            'email' => 'anna@budget.test',
        ]);
        $household->users()->attach($spouse->id, ['role' => 'member']);
        $category = Category::query()->create([
            'household_id' => $household->id,
            'type' => CashflowType::Expense,
            'name' => 'Тест семьи',
            'sort' => 80,
        ]);
        $cash = $household->accounts()->where('name', 'Наличные')->firstOrFail();
        $day = CarbonImmutable::now($household->timezone)->toDateString();

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('openQuick', 'expense')
            ->set('quickAmount', '8,88')
            ->set('quickCategoryId', $category->id)
            ->set('quickAccountId', $cash->id)
            ->set('quickDate', $day)
            ->call('saveQuick')
            ->assertHasNoErrors();

        $activity = HouseholdActivity::query()->first();

        $this->assertNotNull($activity);
        $this->assertSame('Демо: новый расход 8,88 €, Тест семьи', $activity->message);

        $this->actingAs($spouse)
            ->getJson('/family/pulse?after=0')
            ->assertOk()
            ->assertJsonPath('events.0.message', 'Демо: новый расход 8,88 €, Тест семьи');

        $this->actingAs($user)
            ->getJson('/family/pulse?after=0')
            ->assertOk()
            ->assertJsonCount(0, 'events');

        Livewire::actingAs($spouse)
            ->test(Dashboard::class)
            ->assertSee('Тест семьи')
            ->assertSee('8,88 €');
    }

    public function test_an_invite_joins_the_same_household(): void
    {
        Notification::fake();
        $this->seed(DemoBudgetSeeder::class);

        $user = User::query()->where('email', 'demo@budget.test')->firstOrFail();
        $household = $user->currentHousehold();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('memberName', 'Анна')
            ->set('memberEmail', 'Anna@Budget.Test')
            ->call('inviteMember')
            ->assertHasNoErrors()
            ->assertSee('Анна');

        $spouse = User::query()->where('email', 'anna@budget.test')->firstOrFail();

        $this->assertTrue($household->users()->whereKey($spouse->id)->exists());
        $this->assertSame($household->id, $spouse->currentHousehold()->id);
        Notification::assertSentTo($spouse, FamilyInviteNotification::class);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('memberName', 'Анна')
            ->set('memberEmail', 'anna@budget.test')
            ->call('inviteMember')
            ->assertHasErrors('memberEmail');

        $otherHousehold = Household::query()->create([
            'name' => 'Другая',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $other = User::factory()->create(['email' => 'other@budget.test']);
        $otherHousehold->users()->attach($other->id, ['role' => 'owner']);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('memberName', 'Чужой')
            ->set('memberEmail', 'other@budget.test')
            ->call('inviteMember')
            ->assertHasErrors('memberEmail');

        $this->assertFalse($household->users()->whereKey($other->id)->exists());
    }
}
