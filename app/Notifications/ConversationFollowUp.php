<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\EmailThread;

class ConversationFollowUp extends Notification implements ShouldQueue
{
    use Queueable;

    public $thread;

    /**
     * Create a new notification instance.
     */
    public function __construct(EmailThread $thread)
    {
        $this->thread = $thread;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Follow-up Due',
            'message' => 'Conversation: ' . ($this->thread->subject ?: 'No Subject'),
            'action_url' => '/conversations?thread_id=' . $this->thread->id,
            'icon' => 'clock',
            'type' => 'follow_up'
        ];
    }
}
