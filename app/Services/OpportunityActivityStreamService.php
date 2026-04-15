<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\OpportunityActivity;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OpportunityActivityStreamService
{
    /**
     * Global opportunity stream: CRM activities (same family as Opportunity Detail page) plus
     * orphan OpportunityComment rows not already mirrored as comment activities.
     */
    public function streamItemsCollection(Opportunity $opportunity): Collection
    {
        $activities = OpportunityActivity::where('opportunity_id', $opportunity->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        $comments = $opportunity->comments()->with('user')->orderBy('created_at', 'desc')->get();

        $syntheticFromComments = $comments->filter(function ($comment) use ($activities) {
            return ! $activities->contains(function ($activity) use ($comment) {
                if ($activity->activity_type !== 'comment') {
                    return false;
                }
                if ((int) $activity->user_id !== (int) $comment->user_id) {
                    return false;
                }
                if (($activity->description ?? '') !== ($comment->comment ?? '')) {
                    return false;
                }
                $a = $activity->created_at;
                $c = $comment->created_at;
                if ($a instanceof CarbonInterface && $c instanceof CarbonInterface) {
                    return abs($a->diffInSeconds($c)) <= 2;
                }

                return false;
            });
        })->map(function ($comment) {
            $user = $comment->user;

            return (object) [
                'id' => 'ocm_'.$comment->id,
                'activity_type' => 'comment',
                'title' => ($user?->name ?? 'User').' added a comment',
                'description' => $comment->comment,
                'created_at' => $comment->created_at,
                'field_changed' => null,
                'user_id' => $comment->user_id,
                'user' => $user ? (object) [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar_url ?? null,
                ] : null,
            ];
        });

        return $activities->merge($syntheticFromComments)
            ->sortByDesc(function ($row) {
                $d = $row->created_at ?? null;

                return $d instanceof CarbonInterface ? $d->timestamp : 0;
            })
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function previewItems(Opportunity $opportunity, int $limit = 3): array
    {
        return $this->streamItemsCollection($opportunity)
            ->take($limit)
            ->map(fn ($item) => $this->serializeItem($item))
            ->values()
            ->all();
    }

    /**
     * @param  OpportunityActivity|object  $item
     * @return array<string, mixed>
     */
    public function serializeItem($item): array
    {
        if ($item instanceof OpportunityActivity) {
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
            ];
        }

        return $this->serializeGenericObject($item);
    }

    /**
     * @param  object  $obj
     * @return array<string, mixed>
     */
    protected function serializeGenericObject(object $obj): array
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
            $avatar = $user->avatar_url ?? $user->avatar ?? null;
            $userArr = [
                'id' => $user->id ?? null,
                'name' => $user->name ?? 'User',
                'avatar' => $avatar,
            ];
        }

        return [
            'id' => $obj->id ?? null,
            'activity_type' => $obj->activity_type ?? 'comment',
            'title' => $obj->title ?? '',
            'description' => $obj->description ?? '',
            'created_at' => $createdAt,
            'field_changed' => $obj->field_changed ?? null,
            'user_id' => $obj->user_id ?? null,
            'user' => $userArr,
        ];
    }
}
