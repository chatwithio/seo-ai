<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleOauthToken extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_onboarding' => 'boolean',
        'onboarding_step' => 'integer',
        'onboarding_selected_site_ids' => 'array',
        'onboarding_sites_synced_at' => 'datetime',
        'onboarding_sites_selected_at' => 'datetime',
        'onboarding_keywords_imported_at' => 'datetime',
        'onboarding_first_content_at' => 'datetime',
        'onboarding_first_content_skipped_at' => 'datetime',
        'onboarding_publishing_reviewed_at' => 'datetime',
        'onboarding_content_settings_reviewed_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    public function sites()
    {
        return $this->hasMany(GscSite::class, 'google_oauth_token_id');
    }
}
