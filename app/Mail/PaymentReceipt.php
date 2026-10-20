<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceipt extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment)
    {
        $this->onQueue('payments');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibo de pago — Wecar',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-receipt',
            with: [
                'payment' => $this->payment,
                'subscription' => $this->payment->subscription,
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
