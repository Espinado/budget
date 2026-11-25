<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_guests_are_asked_to_log_in(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Скачать')
            ->assertSee('display-mode: standalone', false)
            ->assertSee('downloads/FamilyMoney-Setup.exe', false)
            ->assertDontSee('>К бюджету<', false)
            ->assertDontSee('id="install-banner"', false);
        $this->get('/budget')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }
}
