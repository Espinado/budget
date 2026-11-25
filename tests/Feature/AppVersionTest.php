<?php

namespace Tests\Feature;

use App\Support\AppRelease;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_the_running_page_can_see_when_a_newer_release_is_deployed(): void
    {
        $version = AppRelease::current();

        $response = $this->get('/app-version')
            ->assertOk()
            ->assertJson(['version' => $version]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));

        $this->get('/login')
            ->assertOk()
            ->assertSee('name="app-version" content="'.$version.'"', false)
            ->assertSee('На экран Домой')
            ->assertSee('id="install-ios"', false);
    }
}
