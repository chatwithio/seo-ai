<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAuditLog extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($log) {
            if (auth()->check() && !$log->user_id) {
                $log->user_id = auth()->id();
            }
        });
    }

    protected $casts = [
        'context' => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }
}
