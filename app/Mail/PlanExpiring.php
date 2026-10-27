<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class PlanExpiring extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $planName,
        public string $subjectName,
        public ?Carbon $expiresAt,
        public string $renewalUrl,
        public float $amount,
        public int $daysRemaining,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->daysRemaining <= 0
                ? 'Tu plan vence hoy — Wecar'
                : 'Tu plan vence en '.$this->daysRemaining.' días — Wecar',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.plan-expiring',
            with: [
                'recipientName' => $this->recipientName,
                'planName' => $this->planName,
                'subjectName' => $this->subjectName,
                'expiresAt' => $this->expiresAt,
                'renewalUrl' => $this->renewalUrl,
                'amount' => $this->amount,
                'daysRemaining' => $this->daysRemaining,
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
