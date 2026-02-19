<?php

namespace Ai\AiAgent\Models;

use Common\Core\BaseModel;
use Common\Files\Actions\Deletion\PermanentlyDeleteEntries;
use Common\Files\FileEntry;
use Common\Tags\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class AiAgentDocument extends BaseModel
{
    use Searchable;

    const MODEL_TYPE = 'aiAgentDocument';

    protected $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'file_entry_id' => 'integer',
        'scan_pending' => 'boolean',
        'scan_failed' => 'boolean',
    ];

    protected static function booted()
    {
        parent::booted();

        static::creating(function (AiAgentDocument $document) {
            if (!$document->content_hash && $document->markdown) {
                $document->content_hash = static::hashContent(
                    $document->markdown,
                );
            }
        });
    }

    public function fileEntry(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class);
    }

    public function chunks()
    {
        return $this->morphMany(AiAgentChunk::class, 'chunkable');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function aiAgents()
    {
        return $this->morphToMany(AiAgent::class, 'ai_agentable');
    }

    public function markAsFailed(): void
    {
        $this->update([
            'scan_pending' => false,
            'scan_failed' => true,
            'markdown' => null,
            'content_hash' => null,
        ]);
    }

    public function markAsScanned(): void
    {
        $this->update([
            'scan_pending' => false,
            'scan_failed' => false,
        ]);
    }

    public static function hashContent(string $content): string
    {
        return hash('xxh3', $content);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'file_entry' => $this->fileEntry,
            'markdown' => $this->markdown,
            'tags' => $this->tags->pluck('name'),
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
        ];
    }

    public function makeSearchableUsing($models)
    {
        return $models->load(['fileEntry', 'tags']);
    }

    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['fileEntry', 'tags']);
    }

    public static function filterableFields(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
            'scan_failed',
            'scan_pending',
        ];
    }

    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->fileEntry->name,
            'description' => $this->fileEntry->type,
            'image' => null,
            'model_type' => self::MODEL_TYPE,
        ];
    }

    public static function getModelTypeAttribute(): string
    {
        return self::MODEL_TYPE;
    }
}
