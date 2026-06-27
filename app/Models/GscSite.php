<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GscSite extends Model
{
    protected $guarded = [];

    public function keywords()
    {
        return $this->hasMany(SeoKeyword::class, 'site_id');
    }

    public function keywordMetrics()
    {
        return $this->hasMany(GscKeywordMetric::class, 'site_id');
    }
}
