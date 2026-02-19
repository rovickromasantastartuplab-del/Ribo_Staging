<?php

namespace Ai\AiAgent\Models;

use Common\Core\BaseModel;
use Common\Files\Actions\SyncFileEntryModels;
use Common\Files\FileEntry;
use Common\Files\Traits\HasAttachedFileEntries;
use Illuminate\Support\Arr;
use Laravel\Scout\Searchable;

class AiAgentFlow extends BaseModel
{
    use Searchable, HasAttachedFileEntries;

    const MODEL_TYPE = 'aiAgentFlow';

    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array',
    ];

    public function aiAgents()
    {
        return $this->morphToMany(AiAgent::class, 'ai_agentable');
    }

    public function attachments()
    {
        return $this->attachedFileEntriesRelation('attachments');
    }

    public function syncAttachments()
    {
        $entryIds = [];
        foreach ($this->config['nodes'] ?? [] as $node) {
            $entryIds = array_merge(
                $entryIds,
                Arr::get($node, 'data.attachmentIds', []),
            );

            if ($node['type'] === 'cards') {
                $fileNames = [];
                foreach ($node['data']['cards'] ?? [] as $card) {
                    $name = (new SyncFileEntryModels())->entryFileNameFromUrl(
                        $card['image'] ?? '',
                    );
                    if ($name) {
                        $fileNames[] = $name;
                    }
                }
                $entryIds = array_merge(
                    $entryIds,
                    FileEntry::whereIn('file_name', $fileNames)
                        ->pluck('id')
                        ->toArray(),
                );
            }
        }

        $this->attachments()->sync(array_unique($entryIds));
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
        ];
    }

    public static function filterableFields(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
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
