<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\LeadCaptureForm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadCaptureAutoReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeadCaptureForm $form,
        public Lead $lead,
    ) {
    }

    public function build()
    {
        $companyName = $this->form->company_name ?: getCompanyName();

        $subject = $this->substitute(
            $this->form->auto_reply_subject ?: ('Thanks for contacting ' . $companyName)
        );
        $content = nl2br(e($this->substitute(
            $this->form->auto_reply_body ?: "Hi {lead_name},\n\nThank you for reaching out. We have received your inquiry and our team will get back to you shortly.\n\n— {company_name}"
        )));

        $fromEmail = config('mail.from.address');
        $fromName = $companyName ?: config('mail.from.name');

        return $this->subject($subject)
            ->from($fromEmail, $fromName)
            ->view('emails.notification')
            ->with([
                'subject' => $subject,
                'content' => $content,
            ]);
    }

    private function substitute(string $text): string
    {
        return strtr($text, [
            '{lead_name}' => $this->lead->name ?? '',
            '{lead_email}' => $this->lead->email ?? '',
            '{lead_phone}' => $this->lead->phone ?? '',
            '{company_name}' => $this->form->company_name ?: getCompanyName(),
            '{form_name}' => $this->form->name,
        ]);
    }
}
