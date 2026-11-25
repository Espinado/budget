<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VaultDocumentMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $filename,
        public string $mime,
        public string $contents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Конфиденциальный документ'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.vault-document',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->contents, $this->filename)->withMime($this->mime),
        ];
    }
}
