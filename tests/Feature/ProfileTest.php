<?php

namespace Tests\Feature;

use App\Livewire\Budget\Settings;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_and_password_change_from_settings(): void
    {
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user = User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
        ]);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $user->households()->attach($household->id, ['role' => 'owner']);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->assertSee('anna@example.com')
            ->assertSet('showEmailForm', false)
            ->assertSet('showPasswordForm', false)
            ->call('openEmailForm')
            ->assertSet('showEmailForm', true)
            ->set('profileEmail', 'not-an-email')
            ->set('currentPassword', 'wrong-pass')
            ->call('saveEmail')
            ->assertHasErrors(['profileEmail', 'currentPassword'])
            ->assertSee('field-invalid')
            ->set('profileEmail', 'other@example.com')
            ->set('currentPassword', 'secret-pass')
            ->call('saveEmail')
            ->assertHasErrors(['profileEmail'])
            ->set('profileEmail', 'anna@family.test')
            ->call('saveEmail')
            ->assertHasNoErrors()
            ->assertSet('showEmailForm', false)
            ->assertSee('Почта обновлена.');

        $this->assertSame('anna@family.test', $user->fresh()->email);
        $this->assertSame('other@example.com', $other->fresh()->email);

        Livewire::actingAs($user->fresh())
            ->test(Settings::class)
            ->call('openPasswordForm')
            ->set('currentPassword', 'secret-pass')
            ->set('newPassword', 'short')
            ->set('newPassword_confirmation', 'other-pass')
            ->call('savePassword')
            ->assertHasErrors(['newPassword'])
            ->assertSet('showPasswordForm', true)
            ->set('newPassword', 'longer-pass')
            ->set('newPassword_confirmation', 'longer-pass')
            ->call('savePassword')
            ->assertHasNoErrors()
            ->assertSet('showPasswordForm', false)
            ->assertSee('Пароль обновлён.');

        $this->assertTrue(Hash::check('longer-pass', $user->fresh()->password));
        $this->assertFalse(Hash::check('secret-pass', $user->fresh()->password));
    }
}
