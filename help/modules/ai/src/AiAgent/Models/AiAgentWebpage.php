<?php

namespace Ai\AiAgent\Models;

use Common\Core\BaseModel;
use Common\Tags\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class AiAgentWebpage extends BaseModel
{
    use Searchable;

    const MODEL_TYPE = 'aiAgentWebpage';

    protected $guarded = [];

    protected $casts = [
        'fully_scanned' => 'boolean',
        'scan_pending' => 'boolean',
        'scan_version' => 'integer',
        'scan_tries' => 'integer',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function (AiAgentWebpage $webpage) {
            if (!$webpage->url_hash) {
                $webpage->url_hash = static::hashUrl($webpage->url);
            }
            if (!$webpage->content_hash && $webpage->markdown) {
                $webpage->content_hash = static::hashContent(
                    $webpage->markdown,
                );
            }
        });
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(AiAgentWebsite::class, 'ai_agent_website_id');
    }

    public function chunks()
    {
        return $this->morphMany(AiAgentChunk::class, 'chunkable');
    }

    public function aiAgents()
    {
        return $this->morphToMany(
            AiAgent::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeSelectAllExceptContent(Builder $query): Builder
    {
        return $query->select([
            $query->qualifyColumn('id'),
            'ai_agent_website_id',
            'url_hash',
            'content_hash',
            'url',
            'title',
            'fully_scanned',
            'scan_pending',
            'last_full_scan_version',
            'scan_version',
            'scan_tries',
            'scan_started_at',
            $query->qualifyColumn('created_at'),
            $query->qualifyColumn('updated_at'),
        ]);
    }

    public static function hashUrl(string $url): string
    {
        return hash('xxh3', $url);
    }

    public static function hashContent(string $content): string
    {
        return hash('xxh3', $content);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'url' => $this->url,
            'markdown' => $this->markdown,
            'tags' => $this->tags
                ->pluck('name')
                ->merge($this->website?->tags->pluck('name')->flatten(1)),
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
        ];
    }

    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['website.tags', 'tags']);
    }

    public function makeSearchableUsing(Collection $models)
    {
        return $models->load(['website.tags', 'tags']);
    }

    public static function filterableFields(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
            'fully_scanned',
            'scan_pending',
        ];
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

    public function toChunkableArray(): array|null
    {
        if ($this->title && $this->url) {
            return [
                'id' => $this->id,
                'title' => $this->title,
                'url' => $this->url,
            ];
        }

        return null;
    }

    public static function getModelTypeAttribute(): string
    {
        return self::MODEL_TYPE;
    }
}
