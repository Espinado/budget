<?php

namespace Tests\Feature;

use App\Mail\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_open_the_support_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Поддержка')
            ->assertSee('name="message"', false)
            ->assertSee('name="subject"', false);

        $this->get('/help')
            ->assertOk()
            ->assertSee('Поддержка')
            ->assertSee('name="message"', false);
    }

    public function test_an_authenticated_user_sees_their_email_prefilled(): void
    {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->actingAs($user)
            ->get('/help')
            ->assertOk()
            ->assertSee('value="anna@example.com"', false);
    }

    public function test_empty_email_or_empty_body_fails(): void
    {
        Mail::fake();

        $this->from('/help')->post('/support', [
            'email' => '',
            'subject' => 'Тема',
            'message' => 'Текст',
        ])->assertRedirect('/help')->assertSessionHasErrorsIn('support', [
            'email' => 'Укажите почту.',
        ]);

        $this->from('/help')->post('/support', [
            'email' => 'a@example.com',
            'subject' => 'Тема',
            'message' => '',
        ])->assertRedirect('/help')->assertSessionHasErrorsIn('support', [
            'message' => 'Напишите сообщение.',
        ]);

        Mail::assertNothingSent();
    }

    public function test_latvian_validation_uses_the_current_locale(): void
    {
        Mail::fake();

        $this->withHeader('Accept-Language', 'lv')
            ->from('/help')
            ->post('/support', [
                'email' => '',
                'subject' => '',
                'message' => 'Teksts',
            ])
            ->assertSessionHasErrorsIn('support', [
                'email' => 'Norādiet e-pastu.',
            ]);

        Mail::assertNothingSent();
    }

    public function test_subject_may_be_empty_and_mail_is_sent(): void
    {
        Mail::fake();

        $this->from('/login')->post('/support', [
            'email' => 'guest@example.com',
            'subject' => '',
            'message' => 'Не получается войти.',
        ])->assertRedirect('/login')->assertSessionHas('support_sent');

        $this->get('/login')
            ->assertSee('Сообщение отправлено. Мы ответим на эту почту.')
            ->assertDontSee('<details id="support" class="rounded-3xl bg-white p-4 shadow-sm ring-1 ring-stone-900/5 sm:p-5" open', false);

        $this->assertSame('rvr@arguss.lv', config('mail.support_address'));

        Mail::assertSent(SupportRequest::class, function (SupportRequest $mail): bool {
            return $mail->hasTo('rvr@arguss.lv')
                && $mail->hasReplyTo('guest@example.com')
                && $mail->senderEmail === 'guest@example.com'
                && $mail->topic === ''
                && $mail->text === 'Не получается войти.';
        });
    }

    public function test_a_signed_in_user_can_send_from_a_different_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->actingAs($user)->from('/help')->post('/support', [
            'email' => 'other@example.com',
            'subject' => 'Вопрос',
            'message' => 'Не сходится сводка.',
        ])->assertRedirect('/help')->assertSessionHas('support_sent');

        Mail::assertSent(SupportRequest::class, function (SupportRequest $mail): bool {
            return $mail->senderEmail === 'other@example.com'
                && $mail->topic === 'Вопрос'
                && $mail->hasTo('rvr@arguss.lv');
        });
    }
}
