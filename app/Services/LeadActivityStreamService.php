<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LeadActivityStreamService
{
    /**
     * Same merged stream as the Lead Detail page: CRM activities, omnichannel events, linked email messages.
     */
    public function streamItemsCollection(Lead $lead): Collection
    {
        $activities = $lead->activities()->orderBy('created_at', 'desc')->get()->map(function ($a) {
            $a->is_lead_event = false;

            return $a;
        });

        $leadEvents = LeadEvent::where('lead_id', $lead->id)->orderBy('received_at', 'desc')->get()->map(function ($e) {
            return (object) [
                'id' => 'evt_'.$e->id,
                'created_at' => $e->received_at,
                'title' => 'Received message via '.ucfirst(str_replace('_', ' ', $e->channel)),
                'description' => $e->summary_text,
                'activity_type' => 'message',
                'is_lead_event' => true,
                'channel' => $e->channel,
                'user' => (object) ['name' => 'System (Omnichannel)', 'avatar' => null],
            ];
        });

        $emailMessages = $lead->emailThreads()->with(['messages.sender', 'gmailAccount'])->get()->flatMap(function ($thread) {
            return $thread->messages->map(function ($message) use ($thread) {
                $isIncoming = strtolower($message->from_email) !== strtolower($thread->gmailAccount->gmail_address);

                return (object) [
                    'id' => 'email_'.$message->id,
                    'is_lead_event' => false,
                    'activity_type' => 'email',
                    'title' => $isIncoming
                        ? 'Email from '.($message->from_name ?: $message->from_email)
                        : 'Email sent to '.(implode(', ', $message->to_emails ?? [])),
                    'description' => $message->body_preview,
                    'created_at' => $message->sent_at ?? $message->created_at,
                    'user' => (object) [
                        'name' => $isIncoming ? ($message->from_name ?: $message->from_email) : ($message->sender->name ?? 'System (Gmail)'),
                        'avatar' => $isIncoming ? null : ($message->sender->avatar ?? null),
                    ],
                    'metadata' => [
                        'thread_id' => $thread->id,
                        'message_id' => $message->id,
                        'is_incoming' => $isIncoming,
                    ],
                ];
            });
        });

        return collect($activities)
            ->merge($leadEvents)
            ->merge($emailMessages)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function previewItems(Lead $lead, int $limit = 3): array
    {
        return $this->streamItemsCollection($lead)
            ->take($limit)
            ->map(fn ($item) => $this->serializeItem($item))
            ->values()
            ->all();
    }

    /**
     * @param  LeadActivity|\stdClass|object  $item
     * @return array<string, mixed>
     */
    public function serializeItem($item): array
    {
        if ($item instanceof LeadActivity) {
            $user = $item->relationLoaded('user') ? $item->user : $item->user()->first();

            return [
                'id' => $item->id,
                'activity_type' => $item->activity_type,
                'title' => $item->title,
                'description' => $item->description,
                'created_at' => $item->created_at?->toIso8601String(),
                'field_changed' => $item->field_changed,
                'user_id' => $item->user_id,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar_url ?? null,
                ] : null,
                'is_lead_event' => false,
            ];
        }

        return $this->serializeGenericObject($item);
    }

    /**
     * @param  object  $obj
     * @return array<string, mixed>
     */
    protected function serializeGenericObject($obj): array
    {
        $createdAt = $obj->created_at ?? null;
        if ($createdAt instanceof CarbonInterface) {
            $createdAt = $createdAt->toIso8601String();
        } elseif ($createdAt !== null && ! is_string($createdAt)) {
            $createdAt = (string) $createdAt;
        }

        $user = $obj->user ?? null;
        $userArr = null;
        if (is_object($user)) {
            $avatar = null;
            if (isset($user->avatar_url)) {
                $avatar = $user->avatar_url;
            } elseif (isset($user->avatar)) {
                $avatar = $user->avatar;
            }
            $userArr = [
                'id' => $user->id ?? null,
                'name' => $user->name ?? 'System',
                'avatar' => $avatar,
            ];
        }

        return [
            'id' => $obj->id ?? null,
            'activity_type' => $obj->activity_type ?? 'unknown',
            'title' => $obj->title ?? '',
            'description' => $obj->description ?? '',
            'created_at' => $createdAt,
            'field_changed' => $obj->field_changed ?? null,
            'user_id' => $obj->user_id ?? null,
            'user' => $userArr,
            'is_lead_event' => $obj->is_lead_event ?? false,
            'metadata' => $obj->metadata ?? null,
            'channel' => $obj->channel ?? null,
        ];
    }
}
