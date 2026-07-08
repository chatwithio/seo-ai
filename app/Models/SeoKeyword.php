<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoKeyword extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::saving(function ($keyword) {
            if (empty($keyword->normalized_query) || empty($keyword->query_hash)) {
                $normalizer = app(\App\Services\SeoKeywordNormalizer::class);
                $keyword->normalized_query = $normalizer->normalize($keyword->query_text);
                $keyword->query_hash = $normalizer->hash($keyword->normalized_query);
            }
        });
    }

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }
}
