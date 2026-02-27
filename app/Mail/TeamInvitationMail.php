<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $invitedUser;
    public string $invitationUrl;
    public string $companyName;

    /**
     * Create a new message instance.
     */
    public function __construct(User $invitedUser, string $invitationUrl, string $companyName)
    {
        $this->invitedUser = $invitedUser;
        $this->invitationUrl = $invitationUrl;
        $this->companyName = $companyName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You\'ve been invited to join :company', ['company' => $this->companyName]),
        );
    }

    /**
     * Get the message headers (anti-spam).
     */
    public function headers(): Headers
    {
        return new Headers(
            messageId: null,
            references: [],
            text: [
                'X-Mailer' => 'RiboCRM Mailer',
                'X-Entity-Ref-ID' => uniqid('ribo-invite-', true),
                'Precedence' => 'bulk',
                'List-Unsubscribe' => '<mailto:' . config('mail.from.address') . '>',
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
        );
    }
}
