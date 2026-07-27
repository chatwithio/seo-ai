<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishingSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'content_api_enabled' => 'boolean',
        'content_api_key' => 'encrypted',
        'auto_publish_enabled' => 'boolean',
        'auto_publish_multiple_channels' => 'boolean',
        'general_webhook_enabled' => 'boolean',
        'general_webhook_secret' => 'encrypted',
        'wordpress_webhook_enabled' => 'boolean',
        'wordpress_webhook_secret' => 'encrypted',
        'wordpress_email_enabled' => 'boolean',
        'weekly_activity_email_enabled' => 'boolean',
        'weekly_ideas_email_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
