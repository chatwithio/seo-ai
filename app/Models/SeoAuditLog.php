<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoAuditLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
    ];
}
