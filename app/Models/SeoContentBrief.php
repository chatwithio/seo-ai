<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoContentBrief extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($brief) {
            if (auth()->check() && !$brief->user_id) {
                $brief->user_id = auth()->id();
            }
        });
    }

    public function group()
    {
        return $this->belongsTo(SeoKeywordGroup::class, 'keyword_group_id');
    }

    public function draft()
    {
        return $this->hasOne(SeoContentDraft::class, 'brief_id');
    }
}
