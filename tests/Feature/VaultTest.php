<?php

namespace Tests\Feature;

use App\Livewire\Budget\Vault;
use App\Models\Household;
use App\Models\SecretEntry;
use App\Models\SecretGroup;
use App\Models\User;
use App\Support\PersonalVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_vault_is_personal_masked_and_needs_account_password(): void
    {
        [$owner, $member] = $this->family();

        $home = $this->actingAs($owner)->get('/budget');
        $home->assertOk()
            ->assertSee('aria-label="Хранение конфиденциальной информации"', false)
            ->assertDontSee('aria-label="Пароли"', false);
        $passwordsPos = mb_strpos($home->getContent(), 'aria-label="Хранение конфиденциальной информации"');
        $settingsPos = mb_strpos($home->getContent(), 'aria-label="Настройки"');
        $this->assertNotFalse($passwordsPos);
        $this->assertNotFalse($settingsPos);
        $this->assertLessThan($settingsPos, $passwordsPos);

        $this->actingAs($owner)
            ->get('/vault')
            ->assertOk()
            ->assertSee('Хранение конфиденциальной информации')
            ->assertDontSee('aria-label="Пароли"', false)
            ->assertSee('Вход в раздел')
            ->assertSee('Запомнить пароль нельзя')
            ->assertDontSee('Новый раздел');

        Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'wrong-pass')
            ->call('unlock')
            ->assertHasErrors(['gatePassword'])
            ->assertSee('Осталось попыток: 2')
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->assertHasNoErrors()
            ->assertSee('Новый раздел')
            ->assertDontSee('Раздел с названием, внутри него файлы. Другой человек из семьи их не видит.')
            ->assertDontSee('Закрыть раздел')
            ->assertDontSee('Запомнить')
            ->call('openGroupForm')
            ->set('groupName', 'Банковские коды')
            ->call('saveGroup')
            ->assertSee('Банковские коды')
            ->call('openGroup', SecretGroup::query()->where('user_id', $owner->id)->value('id'))
            ->call('openEntryForm')
            ->set('entryName', 'pin1')
            ->set('entryPayload', '4821')
            ->call('saveEntry')
            ->assertSee('pin1')
            ->assertSee('••••••••')
            ->assertDontSee('4821')
            ->call('toggleReveal', SecretEntry::query()->where('user_id', $owner->id)->value('id'))
            ->assertSee('4821')
            ->call('toggleReveal', SecretEntry::query()->where('user_id', $owner->id)->value('id'))
            ->assertDontSee('4821')
            ->assertSee('••••••••');

        $group = SecretGroup::query()->where('user_id', $owner->id)->firstOrFail();
        $entry = SecretEntry::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('Банковские коды', $group->name);
        $this->assertSame('pin1', $entry->name);
        $this->assertSame('4821', $entry->payload);
        $this->assertNotSame('4821', $entry->getRawOriginal('payload'));

        Livewire::actingAs($member)
            ->test(Vault::class)
            ->set('gatePassword', 'other-pass')
            ->call('unlock')
            ->assertSee('Пока нет разделов')
            ->assertDontSee('pin1')
            ->assertDontSee('>Банковские коды<', false);

        $this->assertSame(0, SecretGroup::query()->where('user_id', $member->id)->count());

        Livewire::actingAs($owner)
            ->test(Vault::class)
            ->assertSee('Вход в раздел')
            ->assertDontSee('Банковские коды')
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->assertSee('Банковские коды')
            ->assertDontSee('Закрыть раздел');

        PersonalVault::lock();

        Livewire::actingAs($owner)
            ->test(Vault::class)
            ->assertSee('Вход в раздел')
            ->assertDontSee('Банковские коды');
    }

    public function test_deletes_need_password_and_three_failures_block_the_vault(): void
    {
        [$owner] = $this->family();

        $group = SecretGroup::query()->create([
            'user_id' => $owner->id,
            'name' => 'Банковские коды',
            'sort' => 10,
        ]);
        $entry = SecretEntry::query()->create([
            'secret_group_id' => $group->id,
            'user_id' => $owner->id,
            'name' => 'pin1',
            'payload' => '4821',
            'sort' => 10,
        ]);

        $page = Livewire::actingAs($owner)
            ->test(Vault::class)
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->call('openGroup', $group->id)
            ->call('askDeleteEntry', $entry->id)
            ->assertSee('Удалить код «pin1»?')
            ->set('deletePassword', 'wrong-pass')
            ->call('confirmDelete')
            ->assertHasErrors(['deletePassword'])
            ->assertSee('Осталось попыток: 2');

        $this->assertTrue(SecretEntry::query()->whereKey($entry->id)->exists());

        $page->set('deletePassword', 'secret-pass')
            ->call('confirmDelete')
            ->assertHasNoErrors()
            ->assertSee('Код удалён.');

        $this->assertFalse(SecretEntry::query()->whereKey($entry->id)->exists());

        $page->call('askDeleteGroup', $group->id)
            ->set('deletePassword', 'secret-pass')
            ->call('confirmDelete')
            ->assertSee('Раздел удалён.');

        $this->assertFalse(SecretGroup::query()->whereKey($group->id)->exists());

        PersonalVault::lock();

        Livewire::actingAs($owner)
            ->test(Vault::class)
            ->assertSee('Вход в раздел')
            ->assertDontSee('Закрыть раздел')
            ->set('gatePassword', 'bad-1')
            ->call('unlock')
            ->assertHasErrors(['gatePassword'])
            ->assertSee('Осталось попыток: 2')
            ->set('gatePassword', 'bad-2')
            ->call('unlock')
            ->assertSee('Осталось попыток: 1')
            ->set('gatePassword', 'bad-3')
            ->call('unlock')
            ->assertSee('Раздел заблокирован');

        $this->assertTrue(PersonalVault::isBlocked());
        $this->assertNotNull($owner->fresh()->vault_blocked_at);

        // Новый сеанс с «запомнить меня» без ввода пароля блокировку не снимает.
        $this->flushSession();
        $this->actingAs($owner->fresh());
        $this->assertTrue(PersonalVault::isBlocked());

        Livewire::actingAs($owner->fresh())
            ->test(Vault::class)
            ->assertSee('Раздел заблокирован')
            ->assertDontSee('Новый раздел')
            ->set('gatePassword', 'secret-pass')
            ->call('unlock')
            ->assertHasErrors(['gatePassword'])
            ->assertSee('Раздел заблокирован');

        Auth::logout();
        $this->post('/login', [
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
            'remember' => 'on',
        ])->assertRedirect('/budget');

        $this->assertNull($owner->fresh()->vault_blocked_at);
        $this->assertFalse(PersonalVault::isBlocked());
        $this->assertFalse(PersonalVault::isUnlocked());
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function family(): array
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $owner = User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
        ]);
        $member = User::factory()->create([
            'email' => 'boris@example.com',
            'password' => 'other-pass',
        ]);
        $owner->households()->attach($household->id, ['role' => 'owner']);
        $member->households()->attach($household->id, ['role' => 'member']);

        return [$owner, $member];
    }
}
