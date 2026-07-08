<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoKeywordGroup extends Model
{
    protected $guarded = [];

    public function keywords()
    {
        return $this->belongsToMany(SeoKeyword::class, 'seo_keyword_group_keywords', 'group_id', 'keyword_id')
            ->withPivot('role');
    }

    public function primaryKeyword()
    {
        return $this->belongsTo(SeoKeyword::class, 'primary_keyword_id');
    }

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }

    public function brief()
    {
        return $this->hasOne(SeoContentBrief::class, 'keyword_group_id');
    }

    public function drafts()
    {
        return $this->hasMany(SeoContentDraft::class, 'keyword_group_id');
    }
}
