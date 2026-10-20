<?php

namespace App\Mail;

use App\Models\CreditApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditApplicationSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public CreditApplication $application)
    {
        $this->onQueue('credits');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva solicitud de crédito '.$this->application->folio.' — Wecar',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.credit-application-submitted',
            with: [
                'application' => $this->application,
                'documents' => $this->application->documents,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
