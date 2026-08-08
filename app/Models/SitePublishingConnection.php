<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SitePublishingConnection extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'last_tested_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }
}
