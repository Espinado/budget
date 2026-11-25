<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderEmail,
        public string $topic,
        public string $text,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->senderEmail)],
            subject: $this->topic !== '' ? $this->topic : __('Сообщение в поддержку Family Money'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.support-request',
        );
    }
}
