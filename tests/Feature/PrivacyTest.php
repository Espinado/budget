<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_privacy_policy_is_public_and_linked_from_the_open_pages(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Политика конфиденциальности')
            ->assertSee('Редакция от 13 ноября 2026 года.')
            ->assertSee(config('mail.support_address'))
            ->assertSee('личные записи сейфа и загруженные документы')
            ->assertSee('например банковский договор')
            ->assertSee('отправить на почту, адрес которой вы вводите сами')
            ->assertSee('Если уходит последний участник')
            ->assertSee('Сервис предназначен для лиц начиная с 16 лет.')
            ->assertSee('Возраст')
            ->assertDontSee('Если текст изменится')
            ->assertDontSee('Новая редакция заменяет прежнюю на этой странице.');

        $this->get('/')->assertOk()->assertSee(route('privacy'), false);
        $this->get('/register')->assertOk()->assertSee(route('privacy'), false);
        $help = $this->get('/help');
        $help->assertOk()
            ->assertSee('id="delete-account"', false)
            ->assertSee('Внизу настроек есть кнопка «Удалить аккаунт»')
            ->assertSee(route('privacy', ['from' => 'help']), false);
        $this->assertSame(1, substr_count($help->getContent(), route('privacy', ['from' => 'help'])));

        $this->get('/privacy')
            ->assertOk()
            ->assertDontSee('← К помощи');
        $this->get('/privacy?from=register')
            ->assertOk()
            ->assertDontSee('← К помощи');
        $this->get('/privacy?from=help')
            ->assertOk()
            ->assertSee('← К помощи')
            ->assertSee(route('help'), false);
    }

    public function test_settings_link_to_the_privacy_policy(): void
    {
        $user = User::factory()->create();
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee(route('privacy'), false);
    }
}
