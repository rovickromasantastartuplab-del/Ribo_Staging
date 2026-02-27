<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ErrorOccurred extends Mailable
{
    use Queueable, SerializesModels;

    public string $errorMessage;
    public string $stackTrace;
    public string $url;
    public string $userName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $errorMessage, string $stackTrace, string $url, string $userName)
    {
        $this->errorMessage = $errorMessage;
        $this->stackTrace = $stackTrace;
        $this->url = $url;
        $this->userName = $userName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Error Occurred',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.error-occurred',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
