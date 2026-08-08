<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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
        'content_version' => 'integer',
        'featured_image_generated_at' => 'datetime',
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
                    $draft->site_id ??= $brief->group?->site_id;
                } else {
                    $draft->brief_id = null;
                }
            }

            if (! $draft->site_id && $draft->keyword_group_id) {
                $draft->site_id = SeoKeywordGroup::whereKey($draft->keyword_group_id)->value('site_id');
            }

            if (blank($draft->slug) && filled($draft->title)) {
                $draft->slug = Str::slug($draft->title);
            }

            if ($draft->isDirty('html')) {
                $draft->plain_text = trim(strip_tags((string) $draft->html));
            }

            if ($draft->isDirty('featured_image_path') && filled($draft->featured_image_path)) {
                $draft->featured_image_disk ??= config('seo_agent.images.disk', 'public');
                $draft->featured_image_url = Storage::disk($draft->featured_image_disk)->url($draft->featured_image_path);
                $draft->featured_image_status = 'ready';
                $draft->featured_image_generated_at ??= now();
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
                'featured_image_path',
                'featured_image_url',
                'featured_image_alt',
            ])) {
                $draft->api_read_at = null;

                $publishedCurrentVersion = ContentPublicationAttempt::query()
                    ->where('seo_content_draft_id', $draft->id)
                    ->where('content_version', (int) $draft->getOriginal('content_version'))
                    ->where('status', 'succeeded')
                    ->exists();

                if ($publishedCurrentVersion) {
                    $draft->content_version = max(1, (int) $draft->getOriginal('content_version') + 1);
                }
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

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }

    public function contentImprovement()
    {
        return $this->belongsTo(SeoContentImprovement::class, 'content_improvement_id');
    }

    public function publicationAttempts()
    {
        return $this->hasMany(ContentPublicationAttempt::class, 'seo_content_draft_id');
    }
}
