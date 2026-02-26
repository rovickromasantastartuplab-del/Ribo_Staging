<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailTemplate extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $content;
    public $fromEmail;
    public $fromName;
    public $attachmentPath;

    /**
     * Create a new message instance.
     */
    public function __construct($subject, $content, $fromEmail, $fromName, $attachmentPath = null)
    {
        $this->subject = $subject;
        $this->content = $content;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->attachmentPath = $attachmentPath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $mail = $this->subject($this->subject)
            ->from($this->fromEmail, $this->fromName)
            ->view('emails.notification')
            ->with([
                'subject' => $this->subject,
                'content' => $this->content,
            ]);

        if ($this->attachmentPath && file_exists($this->attachmentPath)) {
            $mail->attach($this->attachmentPath);
        }

        return $mail;
    }
}
