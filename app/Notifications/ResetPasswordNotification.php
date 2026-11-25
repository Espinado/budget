<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Восстановление пароля Family Money'))
            ->greeting(__('Здравствуйте!'))
            ->line(__('Вы запросили новый пароль для Family Money.'))
            ->action(__('Задать новый пароль'), $this->resetUrl($notifiable))
            ->line(__('Ссылка действует 60 минут. Если вы не запрашивали смену пароля, ничего делать не нужно.'))
            ->salutation('Family Money');
    }
}
