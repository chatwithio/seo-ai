<?php

namespace App\Models;

use App\Services\SeoKeywordNormalizer;
use Illuminate\Database\Eloquent\Model;

class SeoKeyword extends Model
{
    protected $guarded = [];

    protected static function booted()
    {
        static::saving(function ($keyword) {
            if (empty($keyword->normalized_query) || empty($keyword->query_hash)) {
                $normalizer = app(SeoKeywordNormalizer::class);
                $keyword->normalized_query = $normalizer->normalize($keyword->query_text);
                $keyword->query_hash = $normalizer->hash($keyword->normalized_query);
            }
        });

        static::creating(function ($keyword) {
            if (! $keyword->user_id) {
                $keyword->user_id = $keyword->site_id
                    ? GscSite::whereKey($keyword->site_id)->value('user_id')
                    : auth()->id();
            }
        });
    }

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }
}
