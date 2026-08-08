<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GscSite extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'agent_enabled' => 'boolean',
        'auto_content_enabled' => 'boolean',
        'auto_content_count' => 'integer',
        'auto_content_interval_days' => 'integer',
        'content_length' => 'integer',
        'content_keyword_density' => 'decimal:1',
        'auto_content_last_run_at' => 'datetime',
        'last_imported_at' => 'datetime',
        'keywords_updated_at' => 'datetime',
    ];

    public function keywords()
    {
        return $this->hasMany(SeoKeyword::class, 'site_id');
    }

    public function keywordMetrics()
    {
        return $this->hasMany(GscKeywordMetric::class, 'site_id');
    }

    public function googleOauthToken()
    {
        return $this->belongsTo(GoogleOauthToken::class, 'google_oauth_token_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contentImprovements()
    {
        return $this->hasMany(SeoContentImprovement::class, 'site_id');
    }

    public function publishingConnections()
    {
        return $this->hasMany(SitePublishingConnection::class, 'site_id');
    }

    protected static function booted()
    {
        static::creating(function ($site) {
            if (auth()->check() && ! $site->user_id) {
                $site->user_id = auth()->id();
            }
        });
    }

    /**
     * @return array{language: string, density: string, length: string, hint: string}
     */
    public function contentGenerationOptions(): array
    {
        return [
            'language' => $this->content_language ?: 'English',
            'density' => (string) ($this->content_keyword_density ?: '1.5'),
            'length' => (string) ($this->content_length ?: 1000),
            'hint' => (string) ($this->content_instructions ?? ''),
        ];
    }
}
