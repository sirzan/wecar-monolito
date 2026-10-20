<?php

namespace App\Mail;

use App\Models\Agency;
use App\Models\AgencyMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgencyInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Agency $agency,
        public AgencyMember $member,
    ) {
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación a '.$this->agency->name.' en Wecar',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.agency-invitation',
            with: [
                'agency' => $this->agency,
                'member' => $this->member,
                'url' => route('panel.invitations.accept', $this->member->token),
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
