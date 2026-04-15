<?php

namespace App\Notifications;

use App\Models\EmailThread;
use App\Models\ThreadFollowUpQueue;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateLang;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AutomatedFollowUpSent extends Notification
{
    use Queueable;

    public $thread;
    public $queueItem;
    public $template;

    /**
     * Create a new notification instance.
     */
    public function __construct(EmailThread $thread, ThreadFollowUpQueue $queueItem)
    {
        $this->thread = $thread;
        $this->queueItem = $queueItem;
        $this->template = NotificationTemplate::where('name', 'Conversation Follow Up')->first();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        // Check if the company owner has enabled email for this template (Staff inherit owner settings)
        // For anonymous on-demand notifications (direct email), we default to enabled.
        $isEnabled = true; 
        if ($this->template && method_exists($notifiable, 'creatorId')) {
            $isEnabled = isNotificationTemplateEnabled($this->template->name, $notifiable->creatorId());
        }

        if ($isEnabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->getResolvedContent($notifiable);

        return (new MailMessage)
            ->subject($content['title'])
            ->line($content['message'])
            ->action('View Conversation', url('/conversations?thread_id=' . $this->thread->id))
            ->line('Thank you for using our automation service!');
    }

    /**
     * Get the array representation of the notification for database storing.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $content = $this->getResolvedContent($notifiable);

        return [
            'title' => $content['title'],
            'message' => $content['message'],
            'action_url' => '/conversations?thread_id=' . $this->thread->id,
            'icon' => 'send',
            'type' => 'automated_follow_up'
        ];
    }

    /**
     * Resolve template variables and language.
     */
    protected function getResolvedContent(object $notifiable): array
    {
        $langCode = $notifiable->lang ?? config('app.locale', 'en');
        $companyId = method_exists($notifiable, 'creatorId') ? $notifiable->creatorId() : 1;
 
        $templateLang = NotificationTemplateLang::where('parent_id', $this->template?->id)
            ->where('lang', $langCode)
            ->where('created_by', $companyId)
            ->first() ?? NotificationTemplateLang::where('parent_id', $this->template?->id)
                ->where('lang', 'en')
                ->where('created_by', 1) // Fallback to global English
                ->first();

        $stage = $this->queueItem->stage->stage_number ?? 1;
        $recipient = $this->queueItem->recipient_email;
        $subject = $this->thread->subject ?: 'No Subject';

        $variables = [
            '{stage}' => $stage,
            '{recipient}' => $recipient,
            '{subject}' => $subject,
        ];

        $title = $templateLang ? $templateLang->title : 'Automated Follow-up Sent';
        $message = $templateLang ? $templateLang->content : "Automated follow-up (Stage {$stage}) sent to {$recipient} for thread: {$subject}";

        foreach ($variables as $key => $value) {
            $title = str_replace($key, $value, $title);
            $message = str_replace($key, $value, $message);
        }

        return [
            'title' => $title,
            'message' => $message
        ];
    }
}
