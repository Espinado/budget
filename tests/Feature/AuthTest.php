<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Models\Household;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_gets_a_separate_budget(): void
    {
        $this->post('/register', [
            'name' => 'Анна',
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('opening'));

        $user = User::query()->where('email', 'anna@example.com')->firstOrFail();
        $household = $user->currentHousehold();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('EUR', $household->currency);
        $this->assertSame('Europe/Riga', $household->timezone);
        $this->assertSame(0, $household->accounts()->where('name', 'Наличные деньги')->value('balance_cents'));
        $this->assertSame(
            ['Наличные деньги', 'Дебетная карта', 'Кредитная карта', 'Копилка'],
            $household->accounts()->orderBy('sort')->pluck('name')->all(),
        );
        $this->assertSame(
            [AccountRole::Operating, AccountRole::Operating, AccountRole::Operating, AccountRole::Savings],
            $household->accounts()->orderBy('sort')->get()->map(fn ($account) => $account->role)->all(),
        );
        $this->assertSame(
            ['Зарплата', 'Здоровье', 'Медицина', 'Развлечения', 'Хобби', 'Транспорт', 'Дом', 'Питание'],
            $household->categories()->whereNull('parent_id')->orderBy('sort')->pluck('name')->all(),
        );
        $this->assertSame(7, $household->categories()->whereNull('parent_id')->where('type', 'expense')->count());
        $salary = $household->categories()->where('name', 'Зарплата')->whereNull('parent_id')->firstOrFail();
        $this->assertSame('income', $salary->type->value);
        $this->assertSame('salary', $salary->icon);
        $hobby = $household->categories()->where('name', 'Хобби')->whereNull('parent_id')->firstOrFail();
        $this->assertSame(
            ['Абонемент', 'Экипировка'],
            $hobby->children()->orderBy('sort')->orderBy('id')->pluck('name')->all(),
        );
        $medicine = $household->categories()->where('name', 'Медицина')->whereNull('parent_id')->firstOrFail();
        $this->assertSame(
            ['Семейный врач', 'Приём у специалиста', 'Лекарства'],
            $medicine->children()->orderBy('sort')->orderBy('id')->pluck('name')->all(),
        );
        $transport = $household->categories()->where('name', 'Транспорт')->firstOrFail();
        $car = $household->categories()->where('name', 'Автомобиль')->firstOrFail();
        $this->assertSame($transport->id, $car->parent_id);
        $this->assertSame(
            ['Топливо', 'Обслуживание', 'Покупка расходных материалов', 'Парковка', 'Пропуск'],
            $car->children()->orderBy('sort')->orderBy('id')->pluck('name')->all(),
        );
        \App\Support\DefaultCategories::ensureCar($household->fresh());
        \App\Support\DefaultCategories::ensureMedicine($household->fresh());
        \App\Support\DefaultCategories::ensureHobby($household->fresh());
        $this->assertSame(1, $household->categories()->where('name', 'Автомобиль')->count());
        $this->assertSame(5, $car->children()->count());
        $this->assertSame(1, $household->categories()->where('name', 'Медицина')->whereNull('parent_id')->count());
        $this->assertSame(3, $medicine->children()->count());
        $this->assertSame(1, $household->categories()->where('name', 'Хобби')->whereNull('parent_id')->count());
        $this->assertSame(2, $hobby->children()->count());
        $this->get('/budget')->assertRedirect(route('opening'));
        $this->get('/')
            ->assertOk()
            ->assertSee('К бюджету')
            ->assertSee('display-mode: standalone', false)
            ->assertDontSee('aria-label="Хранение конфиденциальной информации"', false)
            ->assertDontSee('aria-label="Пароли"', false)
            ->assertDontSee('aria-label="Список покупок"', false);
        $this->get(route('opening'))
            ->assertOk()
            ->assertSee('Начальные остатки')
            ->assertSee('Наличные деньги')
            ->assertSee('Дебетная карта')
            ->assertSee('Кредитная карта')
            ->assertSee('Копилка');
    }

    public function test_remember_me_keeps_the_login(): void
    {
        $user = User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
            'remember_token' => null,
        ]);

        $this->post('/login', [
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_a_password_can_be_reset_from_the_email_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'secret-pass',
        ]);
        $household = Household::query()->create([
            'name' => 'Наша семья',
            'currency' => 'EUR',
            'timezone' => 'Europe/Riga',
            'anchor_day' => 1,
        ]);
        $user->households()->attach($household->id, ['role' => 'owner']);

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status');

        $this->post('/forgot-password', ['email' => 'anna@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
                ->assertOk()
                ->assertSee('Новый пароль');

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-secret',
                'password_confirmation' => 'new-secret',
            ])->assertRedirect(route('dashboard'));

            return true;
        });

        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->post('/login', [
            'email' => 'anna@example.com',
            'password' => 'new-secret',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_password_email_uses_the_family_money_template(): void
    {
        $user = User::factory()->create();
        $html = (new ResetPasswordNotification('token'))->toMail($user)->render();

        $this->assertStringContainsString('/icons/family-money-192.png', $html);
        $this->assertStringContainsString('Family Money', $html);
        $this->assertStringContainsString('#0f6e6b', $html);
        $this->assertStringContainsString('семейный бюджет', $html);
        $this->assertStringNotContainsString('All rights reserved.', $html);
    }
}
