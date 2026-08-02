<?php

namespace App\Services;

use App\Models\SeoKeyword;
use Illuminate\Database\Eloquent\Collection;

class WeeklySeoIdeasService
{
    /**
     * @return Collection<int, SeoKeyword>
     */
    public function forUser(int $userId, int $limit = 6): Collection
    {
        return SeoKeyword::query()
            ->contentOpportunitiesForUser($userId)
            ->with('site:id,site_url,name')
            ->orderByDesc('total_impressions')
            ->orderBy('total_clicks')
            ->limit(max(1, min($limit, 12)))
            ->get();
    }

    public function keywordUrl(SeoKeyword $keyword): string
    {
        return url('/admin/seo-keywords').'?'.http_build_query([
            'tableSearch' => $keyword->query_text,
        ]);
    }
}
