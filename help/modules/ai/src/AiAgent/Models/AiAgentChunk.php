<?php

namespace Ai\AiAgent\Models;

use App\HelpCenter\Models\HcArticle;
use App\HelpCenter\Models\HcCategory;
use Common\Core\BaseModel;
use Common\Tags\Tag;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Illuminate\Support\Arr;

class AiAgentChunk extends BaseModel
{
    use Searchable;

    const MODEL_TYPE = 'aiAgentChunk';

    protected $casts = [
        'chunkable_id' => 'integer',
        'parent_chunk_id' => 'integer',
        'vector_id' => 'integer',
    ];

    protected $guarded = [];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $chunk) {
            if (!$chunk->hash) {
                $chunk->hash = static::hashContent($chunk->content);
            }
        });
    }

    public function vector()
    {
        return $this->belongsTo(AiAgentVector::class);
    }

    public function chunkable(): MorphTo
    {
        return $this->morphTo()->constrain([
            AiAgentWebpage::class => fn($q) => $q->select([
                'id',
                'url',
                'title',
                'ai_agent_website_id',
            ]),
            AiAgentDocument::class => fn($q) => $q->select([
                'id',
                'file_entry_id',
            ]),
            HcArticle::class => fn($q) => $q->select(['id', 'title', 'slug']),
            AiAgentSnippet::class => fn($q) => $q->select(['id', 'title']),
        ]);
    }

    public static function searchUsingVector(
        array $vector,
        int $limit = 5,
        string|null $knowledgeScopeTag = null,
        int|null $aiAgentId = null,
    ) {
        if (config('scout.driver') === 'meilisearch') {
            $rawResults = static::search(null, function ($index) use (
                $aiAgentId,
                $vector,
                $limit,
                $knowledgeScopeTag,
            ) {
                $filters = [];
                if ($knowledgeScopeTag) {
                    $filters[] = "tags = $knowledgeScopeTag";
                }
                if ($aiAgentId) {
                    $filters[] = "ai_agent_ids = $aiAgentId";
                }
                $options = [
                    'limit' => $limit,
                    'vector' => $vector,
                    'showRankingScore' => true,
                    'rankingScoreThreshold' => 0.7,
                    'distinct' => 'parent_chunk_id',
                    'filter' => implode(' AND ', $filters),
                    'hybrid' => [
                        'semanticRatio' => 1,
                        'embedder' => static::MODEL_TYPE,
                    ],
                ];

                return collect($index->search(null, $options)->getHits())->map(
                    function ($hit) {
                        $hit['score'] = $hit['_rankingScore'];
                        unset($hit['_rankingScore']);
                        return $hit;
                    },
                );
            })
                ->raw()
                ->take($limit);
        } else {
            // scout engine does not have support for vector search,
            // fallback to brute force comparing all vectors
            $distinctValues = [];
            $rawResults = static::with('vector')
                ->whereNotNull('vector_id')
                ->when($aiAgentId, fn($q) => $q->filterByAiAgentId($aiAgentId))
                ->when(
                    $knowledgeScopeTag,
                    fn($q) => $q->filterByTag($knowledgeScopeTag),
                )
                ->orderBy((new static())->getQualifiedKeyName())
                ->limit(2000)
                ->chunkMap(
                    fn($model) => [
                        ...Arr::except($model->toArray(), ['vector']),
                        'score' => $model->vector
                            ? static::cosineSimilarity(
                                $vector,
                                json_decode($model->vector->vector, true),
                            )
                            : 0,
                    ],
                    100,
                )
                ->filter(function ($model) use ($distinctValues) {
                    if (is_null($model['parent_chunk_id'])) {
                        return true;
                    }

                    if (!in_array($model['parent_chunk_id'], $distinctValues)) {
                        $distinctValues[] = $model['parent_chunk_id'];
                        return true;
                    }

                    return false;
                })
                ->sortByDesc('score')
                ->where('score', '>', 0.5)
                ->values();
        }

        $chunkIds = $rawResults->map(
            fn($chunk) => $chunk['parent_chunk_id'] ?? $chunk['id'],
        );

        $chunks = $chunkIds->isNotEmpty()
            ? static::whereIn('id', $chunkIds)->with('chunkable')->get()
            : collect();

        return $chunks
            ->map(function ($chunk) use ($rawResults) {
                try {
                    $score = $rawResults->first(
                        fn($c) => $c['id'] === $chunk->id ||
                            $c['parent_chunk_id'] === $chunk->id,
                    )['score'];
                } catch (\Exception $e) {
                    $score = 0;
                    report($e);
                }

                return [
                    'id' => $chunk->id,
                    'content' => $chunk->content,
                    'score' => $score,
                    'parent_chunk_id' => $chunk->parent_chunk_id,
                    'chunkable' =>
                        $chunk->chunkable &&
                        method_exists($chunk->chunkable, 'toChunkableArray')
                            ? $chunk->chunkable->toChunkableArray()
                            : null,
                ];
            })
            ->sortByDesc('score')
            ->values();
    }

    #[Scope]
    protected function filterByAiAgentId(
        Builder $query,
        int $aiAgentId,
    ): Builder {
        return $query->join(
            'ai_agentables',
            fn($join) => $join
                ->on('ai_agentables.ai_agentable_id', '=', 'chunkable_id')
                ->on('ai_agentables.ai_agentable_type', '=', 'chunkable_type')
                ->where('ai_agentables.ai_agent_id', $aiAgentId),
        );
    }

    #[Scope]
    protected function filterByTag(Builder $query, string $tag): Builder
    {
        // directly tagged chunkables
        $directlyTagged = DB::table('tags')
            ->select([
                'taggables.taggable_type as type',
                'taggables.taggable_id as id',
            ])
            ->join('taggables', 'tags.id', '=', 'taggables.tag_id')
            ->where('tags.name', $tag)
            ->whereIn('taggables.taggable_type', [
                AiAgentDocument::MODEL_TYPE,
                AiAgentWebpage::MODEL_TYPE,
                AiAgentSnippet::MODEL_TYPE,
                HcArticle::MODEL_TYPE,
            ]);

        // articles via category tags
        $articlesViaCategory = DB::table('tags')
            ->select([
                DB::raw("'" . HcArticle::MODEL_TYPE . "' as type"),
                'category_article.article_id as id',
            ])
            ->join('taggables', 'tags.id', '=', 'taggables.tag_id')
            ->join(
                'category_article',
                'taggables.taggable_id',
                '=',
                'category_article.category_id',
            )
            ->where('tags.name', $tag)
            ->where('taggables.taggable_type', HcCategory::MODEL_TYPE);

        // webpages via website tags
        $webpagesViaWebsite = DB::table('tags')
            ->select([
                DB::raw("'" . AiAgentWebpage::MODEL_TYPE . "' as type"),
                'ai_agent_webpages.id as id',
            ])
            ->join('taggables', 'tags.id', '=', 'taggables.tag_id')
            ->join(
                'ai_agent_webpages',
                'taggables.taggable_id',
                '=',
                'ai_agent_webpages.ai_agent_website_id',
            )
            ->where('tags.name', $tag)
            ->where('taggables.taggable_type', AiAgentWebsite::MODEL_TYPE);

        $validChunkables = $directlyTagged
            ->union($articlesViaCategory)
            ->union($webpagesViaWebsite);

        return $query->joinSub(
            $validChunkables,
            'valid_chunkables',
            fn($join) => $join
                ->on('chunkable_type', '=', 'valid_chunkables.type')
                ->on('chunkable_id', '=', 'valid_chunkables.id'),
        );
    }

    protected static function cosineSimilarity(
        array $vector1,
        array $vector2,
    ): ?float {
        // Ensure both vectors are non-empty and have the same dimensions
        if (count($vector1) !== count($vector2) || count($vector1) === 0) {
            return null;
        }

        // Calculate dot product and magnitudes of both vectors
        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] ** 2;
            $magnitude2 += $vector2[$i] ** 2;
        }

        // Avoid division by zero by checking magnitudes
        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return null;
        }

        // Calculate cosine similarity
        return $dotProduct / (sqrt($magnitude1) * sqrt($magnitude2));
    }

    public function scopeSelectWithoutContent(Builder $query): Builder
    {
        return $query->select([
            'id',
            'hash',
            'parent_id',
            'temp_id',
            'created_at',
            'updated_at',
        ]);
    }

    public static function hashContent(string $content): string
    {
        return hash('xxh3', $content);
    }

    public static function filterableFields(): array
    {
        return [
            'id',
            'hash',
            'parent_chunk_id',
            'tags',
            'ai_agent_ids',
            'chunkable_id',
            'chunkable_type',
            'created_at',
            'updated_at',
        ];
    }

    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->hash,
            'description' => null,
            'image' => null,
            'model_type' => self::MODEL_TYPE,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return !is_null($this->vector_id) &&
            (!$this->relationLoaded('vector') || !!$this->vector);
    }

    public function makeSearchableUsing(
        \Illuminate\Database\Eloquent\Collection $models,
    ) {
        return $models->load([
            'vector',
            'chunkable' => function (MorphTo $morphTo) {
                $morphTo
                    ->morphWith([
                        HcArticle::class => ['categories.tags'],
                        AiAgentWebpage::class => ['website'],
                    ])
                    ->with([
                        'tags',
                        'aiAgents' => fn($q) => $q->select([
                            $q->qualifyColumn('id'),
                        ]),
                    ]);
            },
        ]);
    }

    public function makeAllSearchableUsing($query)
    {
        return $query->with([
            'vector',
            'chunkable' => function (MorphTo $morphTo) {
                $morphTo
                    ->morphWith([
                        HcArticle::class => ['categories.tags'],
                        AiAgentWebpage::class => ['website'],
                    ])
                    ->with([
                        'tags',
                        'aiAgents' => fn($q) => $q->select([
                            $q->qualifyColumn('id'),
                        ]),
                    ]);
            },
        ]);
    }

    public function toSearchableArray(): array
    {
        $tags = [];

        if ($this->chunkable) {
            if ($this->chunkable_type === HcArticle::MODEL_TYPE) {
                $tags = $this->chunkable->categories
                    ->pluck('tags')
                    ->flatten(1)
                    ->pluck('name')
                    ->merge($this->chunkable->tags->pluck('name'))
                    ->toArray();
            } elseif ($this->chunkable_type === AiAgentWebpage::MODEL_TYPE) {
                $tags = $this->chunkable->website->tags
                    ->pluck('name')
                    ->merge($this->chunkable->tags->pluck('name'))
                    ->toArray();
            } else {
                $tags = $this->chunkable->tags->pluck('name')->toArray();
            }
        }

        return [
            'id' => $this->id,
            'hash' => $this->hash,
            '_vectors' => [
                self::MODEL_TYPE => $this->vector_id
                    ? json_decode($this->vector->vector)
                    : null,
            ],
            'ai_agent_ids' =>
                $this->chunkable?->aiAgents->pluck('id')->toArray() ?? [],
            'chunkable_id' => $this->chunkable_id,
            'chunkable_type' => $this->chunkable_type,
            'parent_chunk_id' => $this->parent_chunk_id,
            'tags' => $tags,
            'content' => $this->content,
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
        ];
    }

    public static function getModelTypeAttribute(): string
    {
        return self::MODEL_TYPE;
    }
}
