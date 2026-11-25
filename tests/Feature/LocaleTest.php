<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_latvian_accept_language_shows_latvian_ui(): void
    {
        $this->withHeader('Accept-Language', 'lv-LV,lv;q=0.9,en;q=0.8')
            ->get('/login')
            ->assertOk()
            ->assertSee('Ienākt', false)
            ->assertDontSee('>Войти<', false)
            ->assertSee('lang="lv"', false);
    }

    public function test_russian_accept_language_keeps_russian_ui(): void
    {
        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9')
            ->get('/login')
            ->assertOk()
            ->assertSee('Войти', false)
            ->assertSee('lang="ru"', false);
    }

    public function test_default_without_header_stays_russian(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Войти', false)
            ->assertSee('Запомнить меня', false);
    }

    public function test_english_browser_falls_back_to_russian(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/login')
            ->assertOk()
            ->assertSee('Войти', false)
            ->assertDontSee('Ienākt', false);
    }

    public function test_help_and_auth_pages_ok_in_latvian(): void
    {
        $this->withHeader('Accept-Language', 'lv')
            ->get('/help')
            ->assertOk()
            ->assertSee('Palīdzība', false)
            ->assertSee('Saturs', false);

        $this->withHeader('Accept-Language', 'lv')
            ->get('/register')
            ->assertOk()
            ->assertSee('Reģistrācija', false);

        $this->withHeader('Accept-Language', 'lv')
            ->get('/forgot-password')
            ->assertOk();
    }

    public function test_help_still_russian_by_default_for_existing_assertions(): void
    {
        $this->get('/help')
            ->assertOk()
            ->assertSee('Помощь')
            ->assertSee('Всего денег на счетах');
    }
}
