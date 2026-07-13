<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoKeywordGroup extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($group) {
            if (! $group->user_id) {
                $group->user_id = $group->site_id
                    ? GscSite::whereKey($group->site_id)->value('user_id')
                    : auth()->id();
            }
        });
    }

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
