<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends BaseModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'image',
        'main_image',
        'main_image_id',
        'additional_images',
        'additional_image_ids',
        'category_id',
        'brand_id',
        'tax_id',
        'status',
        'created_by',
        'assigned_to',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'additional_images' => 'array',
        'additional_image_ids' => 'array',
    ];

    protected $appends = ['main_image_url', 'additional_image_urls'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function opportunities(): BelongsToMany
    {
        return $this->belongsToMany(Opportunity::class, 'opportunity_products')
            ->withPivot('quantity', 'unit_price', 'total_price')
            ->withTimestamps();
    }

    public function quotes(): BelongsToMany
    {
        return $this->belongsToMany(Quote::class, 'quote_products')
            ->withPivot('quantity', 'unit_price', 'total_price')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('main')
            ->singleFile();
            
        $this->addMediaCollection('additional');
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->performOnCollections('main', 'additional')
            ->nonQueued();
    }

    public function getMainImageUrlAttribute()
    {
        if ($this->main_image_id) {
            $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($this->main_image_id);
            return $media && $media->exists() ? $media->getUrl() : $this->getDefaultImageUrl();
        }
        $media = $this->getFirstMedia('main');
        return $media && $media->exists() ? $media->getUrl() : $this->getDefaultImageUrl();
    }

    public function getDefaultImageUrl()
    {
        return $this->image ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik0xMDAgNjBMMTQwIDgwVjE0MEwxMDAgMTYwTDYwIDE0MFY4MEwxMDAgNjBaIiBzdHJva2U9IiM5Q0EzQUYiIHN0cm9rZS13aWR0aD0iMiIgZmlsbD0iI0U1RTdFQiIvPgo8Y2lyY2xlIGN4PSI4NSIgY3k9Ijk1IiByPSI4IiBmaWxsPSIjOUNBM0FGIi8+CjxwYXRoIGQ9Ik03MCAxMzBMODUgMTE1TDEwMCAxMzBMMTMwIDEwMEwxMzAgMTMwSDcwWiIgZmlsbD0iIzlDQTNBRiIvPgo8L3N2Zz4=';
    }

    public function getAdditionalImageUrlsAttribute()
    {
        if ($this->additional_image_ids) {
            return collect($this->additional_image_ids)->map(function ($mediaId) {
                $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($mediaId);
                return $media ? [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb')
                ] : null;
            })->filter();
        }
        return $this->getMedia('additional')->map(function ($media) {
            return [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'thumb_url' => $media->getUrl('thumb')
            ];
        });
    }

    public function toArray()
    {
        $array = parent::toArray();
        
        // Add media in the format expected by frontend
        $mediaArray = [];
        
        // Add main image - check if media still exists
        $mainMedia = $this->getFirstMedia('main');
        if ($mainMedia && $mainMedia->exists()) {
            $mediaArray[] = [
                'id' => $mainMedia->id,
                'collection_name' => 'main',
                'original_url' => $mainMedia->getUrl(),
                'thumb_url' => $mainMedia->getUrl('thumb')
            ];
        }
        
        // Add additional images - filter out deleted media
        foreach ($this->getMedia('additional') as $media) {
            if ($media->exists()) {
                $mediaArray[] = [
                    'id' => $media->id,
                    'collection_name' => 'additional',
                    'original_url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb')
                ];
            }
        }
        
        $array['media'] = $mediaArray;
        $array['has_valid_image'] = !empty($mediaArray) || !empty($this->image);
        $array['display_image_url'] = $this->getMainImageUrlAttribute();
        
        return $array;
    }
}