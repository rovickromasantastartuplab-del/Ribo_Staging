<?php

namespace Ai\AiAgent\Models;

use Common\Core\BaseModel;
use Common\Tags\Tag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class AiAgentWebsite extends BaseModel
{
    use Searchable;

    const MODEL_TYPE = 'aiAgentWebsite';

    protected $guarded = [];

    protected $casts = [
        'scan_pending' => 'boolean',
        'scan_version' => 'integer',
        'scrape_config' => 'array',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function (AiAgentWebsite $website) {
            if (!$website->url_hash) {
                $website->url_hash = static::hashUrl($website->url);
            }
        });
    }

    public function hasPageWithContent(
        string $hash,
        ?int $exceptId = null,
    ): bool {
        return $this->webpages()
            ->where('content_hash', $hash)
            ->when(
                $exceptId,
                fn($query) => $query->where('id', '!=', $exceptId),
            )
            ->exists();
    }

    public function isSinglePage(): bool
    {
        return $this->scan_type === 'single';
    }

    public function syncIsInProgress(): bool
    {
        return $this->scan_pending;
    }

    public function webpages(): HasMany
    {
        return $this->hasMany(AiAgentWebpage::class);
    }

    public function aiAgents()
    {
        return $this->morphToMany(AiAgent::class, 'ai_agentable');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'tags' => $this->tags->pluck('name'),
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
        ];
    }

    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['tags']);
    }

    public function makeSearchableUsing(Collection $models)
    {
        return $models->load(['tags']);
    }

    public static function hashUrl(string $url): string
    {
        return AiAgentWebpage::hashUrl($url);
    }

    public static function filterableFields(): array
    {
        return ['id', 'created_at', 'updated_at', 'scan_pending'];
    }

    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->title,
            'description' => null,
            'image' => null,
            'model_type' => self::MODEL_TYPE,
        ];
    }

    public static function getModelTypeAttribute(): string
    {
        return self::MODEL_TYPE;
    }
}
