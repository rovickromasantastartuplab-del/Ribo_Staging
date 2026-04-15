<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReportVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'email_thread_id',
        'ai_report_job_id',
        'scope',
        'snapshot_json',
        'pdf_path',
        'template_version',
        'download_count',
        'first_downloaded_at',
        'last_downloaded_at',
        'last_downloaded_by',
    ];

    protected $casts = [
        'snapshot_json' => 'array',
        'first_downloaded_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
    ];

    public function lastDownloader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_downloaded_by');
    }
}
