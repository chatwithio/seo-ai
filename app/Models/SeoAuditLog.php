<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoAuditLog extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::saving(function (SeoAuditLog $log): void {
            if ($log->message === null) {
                return;
            }

            // Database exceptions can include the entire failed bulk SQL
            // statement. Store a bounded, valid UTF-8 summary so audit
            // logging never causes a second database exception.
            $message = mb_convert_encoding((string) $log->message, 'UTF-8', 'UTF-8');
            $log->message = Str::limit($message, 10000, '…');
        });

        static::creating(function ($log) {
            if (! $log->user_id) {
                $log->user_id = $log->site_id
                    ? GscSite::whereKey($log->site_id)->value('user_id')
                    : auth()->id();
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
