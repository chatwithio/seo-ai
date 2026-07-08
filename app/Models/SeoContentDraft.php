<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoContentDraft extends Model
{
    protected $guarded = [];

    public function brief()
    {
        return $this->belongsTo(SeoContentBrief::class, 'brief_id');
    }

    public function group()
    {
        return $this->belongsTo(SeoKeywordGroup::class, 'keyword_group_id');
    }
}
