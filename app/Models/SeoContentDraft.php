<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoContentDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'faq' => 'array',
        'internal_link_suggestions' => 'array',
        'quality_checks' => 'array',
        'published_at' => 'datetime',
        'api_read_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($draft) {
            if (! $draft->user_id) {
                $draft->user_id = $draft->keyword_group_id
                    ? SeoKeywordGroup::whereKey($draft->keyword_group_id)->value('user_id')
                    : auth()->id();
            }
        });

        static::updating(function (SeoContentDraft $draft): void {
            if ($draft->isDirty([
                'title',
                'slug',
                'meta_title',
                'meta_description',
                'html',
                'plain_text',
            ])) {
                $draft->api_read_at = null;
            }
        });
    }

    public function brief()
    {
        return $this->belongsTo(SeoContentBrief::class, 'brief_id');
    }

    public function group()
    {
        return $this->belongsTo(SeoKeywordGroup::class, 'keyword_group_id');
    }
}
