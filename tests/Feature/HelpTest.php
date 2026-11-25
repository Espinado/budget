<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manual_is_public_and_linked_from_the_menu(): void
    {
        $this->get('/help')
            ->assertOk()
            ->assertSee('Помощь')
            ->assertSee('Всего денег на счетах')
            ->assertSee('Сводка')
            ->assertDontSee('Баланс отчётного периода')
            ->assertSee('Копилка')
            ->assertSee('На экран Домой')
            ->assertSee('Safari')
            ->assertSee('Добавить приход')
            ->assertSee('Пригласить')
            ->assertSee('Период уже закончился')
            ->assertSee('позиции плана')
            ->assertSee('Список операций')
            ->assertSee('Список покупок')
            ->assertSee('Это список «что купить», не расход.')
            ->assertSee('Операции под строкой плана.')
            ->assertSee('остаток долга уменьшается только на основную сумму')
            ->assertSee('Строки из раздела долгов копирование не переносит')
            ->assertSee('Запомнить пароль для этого раздела нельзя')
            ->assertSee('После трёх неверных попыток раздел блокируется')
            ->assertSee('«Запомнить меня» при входе в Family Money блокировку не снимает')
            ->assertSee('Удаление раздела или кода снова спрашивает пароль')
            ->assertSee('images/help/vault-gate.jpg', false)
            ->assertSee('images/help/vault-groups.jpg', false)
            ->assertSee('images/help/vault-codes.jpg', false)
            ->assertSee('images/help/vault-delete.jpg', false)
            ->assertSee('Личные')
            ->assertSee('Кредиты')
            ->assertSee('в день')
            ->assertSee('дд/мм/гггг')
            ->assertSee('Объекты')
            ->assertSee('Под категорией')
            ->assertSee('Сам родитель')
            ->assertSee('Новый контракт')
            ->assertSee('июньская видна в июне')
            ->assertSee('второй раз в заголовок «Кредит» не добавляется')
            ->assertSee('С чего начать?')
            ->assertSee('Создать аккаунт')
            ->assertSee('Начальные остатки')
            ->assertSee('images/help/register.jpg', false)
            ->assertSee('images/help/opening.jpg', false)
            ->assertSee('Поддержка')
            ->assertSee('у этого контрагента')
            ->assertSee('Дата окончания договора остаётся')
            ->assertSee('Новая ставка действует с ближайшего неоплаченного платежа')
            ->assertSee('сумма может стать больше или меньше')
            ->assertSee('← К бюджету')
            ->assertSee('aria-label="Наверх"', false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('href="'.route('help', ['from' => 'register']).'"', false);

        $this->get('/help?from=register')
            ->assertOk()
            ->assertSee('← К регистрации')
            ->assertSee('href="'.route('register').'"', false)
            ->assertDontSee('← К бюджету');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/help')
            ->assertOk()
            ->assertSee('href="'.route('help').'"', false)
            ->assertSee('>Помощь<', false);
    }
}
