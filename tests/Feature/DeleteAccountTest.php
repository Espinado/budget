<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Enums\CashflowType;
use App\Enums\DebtDirection;
use App\Livewire\Budget\Settings;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Household;
use App\Models\SecretEntry;
use App\Models\SecretGroup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_wrong_password_does_not_delete_the_account(): void
    {
        [$owner] = $this->family();

        Livewire::actingAs($owner)
            ->test(Settings::class)
            ->set('accountDeletionPassword', 'wrong-pass')
            ->call('deleteOwnAccount')
            ->assertHasErrors('accountDeletionPassword');

        $this->assertNotNull(User::query()->find($owner->id));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_another_member_keeps_the_family_budget(): void
    {
        [$owner, $member, $household, $account] = $this->family();
        $category = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Банк',
            'sort' => 1,
        ]);
        $debt = Debt::query()->create([
            'household_id' => $household->id,
            'category_id' => $category->id,
            'direction' => DebtDirection::Borrow,
            'counterparty' => 'Inbank',
        ]);
        $group = SecretGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Банк',
            'sort' => 10,
        ]);
        $entry = SecretEntry::query()->create([
            'secret_group_id' => $group->id,
            'user_id' => $owner->id,
            'name' => 'Карта',
            'payload' => 'secret',
            'sort' => 10,
        ]);
        $memberEntry = SecretEntry::query()->create([
            'secret_group_id' => SecretGroup::query()->create([
                'user_id' => $member->id,
                'name' => 'Свои',
                'sort' => 10,
            ])->id,
            'user_id' => $member->id,
            'name' => 'Почта',
            'payload' => 'member-secret',
            'sort' => 10,
        ]);

        Livewire::actingAs($owner)
            ->test(Settings::class)
            ->set('accountDeletionPassword', 'secret-pass')
            ->call('deleteOwnAccount')
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull(User::query()->find($owner->id));
        $this->assertNull(SecretEntry::query()->find($entry->id));
        $this->assertNotNull(User::query()->find($member->id));
        $this->assertNotNull(SecretEntry::query()->find($memberEntry->id));
        $this->assertNotNull(Household::query()->find($household->id));
        $this->assertSame(12_345, Account::query()->find($account->id)->balance_cents);
        $this->assertSame('Inbank', Debt::query()->find($debt->id)->counterparty);
        $this->assertSame(0, $debt->installments()->count());
    }

    public function test_the_last_member_removes_the_household(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('receipts/cheque.jpg', 'jpeg');

        $owner = User::factory()->create([
            'name' => 'Анна',
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
        ]);
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $owner->households()->attach($household->id, ['role' => 'owner']);
        $category = $household->categories()->create([
            'type' => CashflowType::Expense,
            'name' => 'Еда',
            'sort' => 1,
        ]);
        $period = $household->periods()->create([
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
        ]);
        Transaction::query()->create([
            'household_id' => $household->id,
            'period_id' => $period->id,
            'category_id' => $category->id,
            'type' => CashflowType::Expense,
            'amount_cents' => 100,
            'occurred_on' => '2026-11-02',
            'receipt_path' => 'receipts/cheque.jpg',
            'created_by' => $owner->id,
        ]);
        $group = SecretGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Дом',
            'sort' => 10,
        ]);
        SecretEntry::query()->create([
            'secret_group_id' => $group->id,
            'user_id' => $owner->id,
            'name' => 'Код',
            'payload' => 'secret',
            'sort' => 10,
        ]);

        Livewire::actingAs($owner)
            ->test(Settings::class)
            ->set('accountDeletionPassword', 'secret-pass')
            ->call('deleteOwnAccount')
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull(User::query()->find($owner->id));
        $this->assertNull(Household::query()->find($household->id));
        $this->assertSame(0, SecretEntry::query()->count());
        Storage::disk('public')->assertMissing('receipts/cheque.jpg');
        Auth::logout();
    }

    /**
     * @return array{0: User, 1: User, 2: Household, 3: Account}
     */
    private function family(): array
    {
        $owner = User::factory()->create(['password' => 'secret-pass']);
        $member = User::factory()->create(['password' => 'secret-pass']);
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $owner->households()->attach($household->id, ['role' => 'owner']);
        $member->households()->attach($household->id, ['role' => 'member']);
        $account = $household->accounts()->create([
            'name' => 'Наличные',
            'kind' => 'cash',
            'role' => AccountRole::Operating,
            'balance_cents' => 12_345,
            'sort' => 10,
        ]);

        return [$owner, $member, $household, $account];
    }
}
