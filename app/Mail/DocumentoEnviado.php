<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Envío de un contrato o cotización en PDF al cliente. */
class DocumentoEnviado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $asunto,
        public string $mensaje,
        private string $pdf,
        private string $nombrePdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->asunto);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.documento');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->nombrePdf)->withMime('application/pdf')];
    }
}
