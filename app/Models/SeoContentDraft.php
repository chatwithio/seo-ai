<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoContentDraft extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($draft) {
            if (! $draft->user_id) {
                $draft->user_id = $draft->keyword_group_id
                    ? SeoKeywordGroup::whereKey($draft->keyword_group_id)->value('user_id')
                    : auth()->id();
            }
        });
    }

    public function brief()
    {
        return $this->belongsTo(SeoContentBrief::class, 'brief_id');
    }

    public function group()
    {
        return $this->belongsTo(SeoKeywordGroup::class, 'keyword_group_id');
    }
}
