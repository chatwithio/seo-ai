<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemporaryUserLoginLink extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
