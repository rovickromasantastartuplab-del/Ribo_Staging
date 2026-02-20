<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
        );
    }
}
