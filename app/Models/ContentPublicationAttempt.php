<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPublicationAttempt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'content_version' => 'integer',
        'attempted_at' => 'datetime',
        'succeeded_at' => 'datetime',
    ];

    public function draft()
    {
        return $this->belongsTo(SeoContentDraft::class, 'seo_content_draft_id');
    }

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }
}
