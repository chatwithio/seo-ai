<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoContentImprovement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_keywords' => 'array',
        'period_start' => 'date',
        'period_end' => 'date',
        'scanned_at' => 'datetime',
        'is_current' => 'boolean',
        'recommendation_generated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }

    public function generatedDraft()
    {
        return $this->belongsTo(SeoContentDraft::class, 'generated_draft_id');
    }
}
