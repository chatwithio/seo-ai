<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoContentBrief extends Model
{
    protected $guarded = [];

    public function group()
    {
        return $this->belongsTo(SeoKeywordGroup::class, 'keyword_group_id');
    }

    public function draft()
    {
        return $this->hasOne(SeoContentDraft::class, 'brief_id');
    }
}
