<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadCaptureForm extends BaseModel
{
    use HasFactory;

    /**
     * Lead fields a capture form is allowed to expose. The form's fields_config
     * toggles visibility/required over this fixed pool ("name" is always required).
     */
    public const ALLOWED_FIELDS = [
        'name',
        'email',
        'phone',
        'company',
        'position',
        'website',
        'address',
        'notes',
    ];

    protected $fillable = [
        'created_by',
        'name',
        'slug',
        'description',
        'type',
        'lead_source_id',
        'campaign_id',
        'default_assigned_to',
        'default_lead_status_id',
        'fields_config',
        'company_name',
        'logo_media_id',
        'submit_button_text',
        'thank_you_message',
        'theme',
        'auto_reply_enabled',
        'auto_reply_subject',
        'auto_reply_body',
        'is_active',
    ];

    protected $casts = [
        'fields_config' => 'array',
        'theme' => 'array',
        'auto_reply_enabled' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Generate a globally-unique slug for a form name.
     */
    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'form';
        do {
            $slug = $base . '-' . Str::lower(Str::random(6));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Default fields_config used when a form is created without explicit field setup.
     */
    public static function defaultFieldsConfig(): array
    {
        return collect(self::ALLOWED_FIELDS)->map(fn ($field) => [
            'field' => $field,
            'visible' => in_array($field, ['name', 'email', 'phone', 'notes'], true),
            'required' => $field === 'name',
        ])->all();
    }

    public function publicUrl(): string
    {
        return route('lead-capture.public.show', ['form' => $this->slug]);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assigned_to');
    }

    public function defaultStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'default_lead_status_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(LeadCaptureSubmission::class);
    }
}
