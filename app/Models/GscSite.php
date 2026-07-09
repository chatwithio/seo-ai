<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GscSite extends Model
{
    protected $guarded = [];

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

    protected static function booted()
    {
        static::creating(function ($site) {
            if (auth()->check() && !$site->user_id) {
                $site->user_id = auth()->id();
            }
        });
    }
}
