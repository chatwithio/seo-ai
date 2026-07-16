<?php

namespace App\Models;

use App\Services\SeoKeywordNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoKeyword extends Model
{
    public const CONTENT_READY = 0;

    public const CONTENT_GENERATING = 1;

    public const CONTENT_COMPLETED = 2;

    public const CONTENT_FAILED = 3;

    protected $guarded = [];

    protected $casts = [
        'content_generation_status' => 'integer',
        'content_generated_at' => 'datetime',
    ];

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

    /**
     * @return array{impressions: float, clicks: float}
     */
    public static function performanceAveragesForUser(int $userId): array
    {
        $averages = static::query()
            ->where('user_id', $userId)
            ->where('total_impressions', '>', 0)
            ->selectRaw('AVG(total_impressions) as impressions, AVG(total_clicks) as clicks')
            ->first();

        return [
            'impressions' => (float) ($averages?->impressions ?? 0),
            'clicks' => (float) ($averages?->clicks ?? 0),
        ];
    }

    public function scopeTopByImpressionsForUser(Builder $query, int $userId): Builder
    {
        $averages = static::performanceAveragesForUser($userId);

        return $query
            ->where('user_id', $userId)
            ->whereIn('content_generation_status', [self::CONTENT_READY, self::CONTENT_FAILED])
            ->where('total_impressions', '>', 0)
            ->where('total_impressions', '>=', $averages['impressions']);
    }

    public function scopeContentOpportunitiesForUser(Builder $query, int $userId): Builder
    {
        $averages = static::performanceAveragesForUser($userId);

        return $query
            ->where('user_id', $userId)
            ->whereIn('content_generation_status', [self::CONTENT_READY, self::CONTENT_FAILED])
            ->where('total_impressions', '>', 0)
            ->where('total_impressions', '>=', $averages['impressions'])
            ->where('total_clicks', '<=', $averages['clicks']);
    }
}
