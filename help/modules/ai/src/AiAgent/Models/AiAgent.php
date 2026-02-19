<?php

namespace Ai\AiAgent\Models;

use App\Core\WidgetFlags;
use App\HelpCenter\Models\HcArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AiAgent extends Model
{
    const MODEL_TYPE = 'aiAgent';

    protected $guarded = [];

    protected $casts = [
        'id' => 'integer',
        'enabled' => 'boolean',
        'config' => 'array',
    ];

    public function flows()
    {
        return $this->morphedByMany(
            AiAgentFlow::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function tools()
    {
        return $this->morphedByMany(
            AiAgentTool::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function documents()
    {
        return $this->morphedByMany(
            AiAgentDocument::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function articles()
    {
        return $this->morphedByMany(
            HcArticle::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function snippets()
    {
        return $this->morphedByMany(
            AiAgentSnippet::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function websites()
    {
        return $this->morphedByMany(
            AiAgentWebsite::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function webpages()
    {
        return $this->morphedByMany(
            AiAgentWebpage::class,
            'ai_agentable',
        )->withTimestamps();
    }

    public function getConfig(string $key, mixed $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    public static function getCurrentlyActive(): self|null
    {
        if ($aiAgentId = WidgetFlags::aiAgentId()) {
            return self::find($aiAgentId);
        }

        // always default to the ai agent that was created first
        return self::orderBy('id', 'asc')->first();
    }

    public static function getModelTypeAttribute(): string
    {
        return self::MODEL_TYPE;
    }
}
