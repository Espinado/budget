<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyInviteNotification extends Notification
{
    public function __construct(
        public string $token,
        public string $householdName,
        public string $inviter,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('Вас пригласили в семейный бюджет'))
            ->greeting(__('Здравствуйте!'))
            ->line($this->inviter.__(' приглашает вас в бюджет «').$this->householdName.'».')
            ->line(__('Задайте пароль и входите под своей почтой. Бюджет общий: приходы, расходы и план видны всем членам семьи.'))
            ->action(__('Задать пароль и войти'), $url)
            ->line(__('Ссылка действует 60 минут.'))
            ->salutation('Family Money');
    }
}
