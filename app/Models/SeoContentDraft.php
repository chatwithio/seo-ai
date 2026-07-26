<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        static::saving(function (SeoContentDraft $draft): void {
            $ownerId = (int) ($draft->user_id ?: auth()->id());

            if ($ownerId > 0 && filled($draft->keyword_group_id)) {
                $ownsGroup = SeoKeywordGroup::query()
                    ->where('user_id', $ownerId)
                    ->whereKey($draft->keyword_group_id)
                    ->exists();

                if (! $ownsGroup) {
                    $draft->keyword_group_id = null;
                }
            }

            if (filled($draft->brief_id)) {
                $brief = SeoContentBrief::query()
                    ->when($ownerId > 0, fn ($query) => $query->where('user_id', $ownerId))
                    ->find($draft->brief_id);

                if ($brief) {
                    $draft->keyword_group_id = $brief->keyword_group_id;
                } else {
                    $draft->brief_id = null;
                }
            }

            if (blank($draft->slug) && filled($draft->title)) {
                $draft->slug = Str::slug($draft->title);
            }

            if ($draft->isDirty('html')) {
                $draft->plain_text = trim(strip_tags((string) $draft->html));
            }
        });

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
