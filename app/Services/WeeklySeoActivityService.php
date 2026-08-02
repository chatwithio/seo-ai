<?php

namespace App\Services;

use App\Models\GscKeywordMetric;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class WeeklySeoActivityService
{
    /**
     * @return array<string, string>
     */
    public function forUser(int $userId): array
    {
        $latestReportDate = $this->queryForUser($userId)->max('report_date');

        if (! $latestReportDate) {
            return $this->emptyActivity();
        }

        $currentEnd = CarbonImmutable::parse($latestReportDate)->startOfDay();
        $currentStart = $currentEnd->subDays(6);
        $previousEnd = $currentStart->subDay();
        $previousStart = $previousEnd->subDays(6);

        $current = $this->totalsForPeriod($userId, $currentStart, $currentEnd);
        $previous = $this->totalsForPeriod($userId, $previousStart, $previousEnd);

        return [
            'keyword_count' => Number::format($current['keyword_count']),
            'keyword_change' => $this->percentageChange($current['keyword_count'], $previous['keyword_count']),
            'impressions' => Number::format($current['impressions']),
            'impressions_change' => $this->percentageChange($current['impressions'], $previous['impressions']),
            'clicks' => Number::format($current['clicks']),
            'clicks_change' => $this->percentageChange($current['clicks'], $previous['clicks']),
            'activity_period' => $currentStart->format('M j').'–'.$currentEnd->format('M j, Y'),
        ];
    }

    /**
     * @return array{keyword_count: int, impressions: int, clicks: int}
     */
    private function totalsForPeriod(
        int $userId,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $query = $this->queryForUser($userId)
            ->whereBetween('gsc_keyword_metrics.report_date', [
                $start->toDateString(),
                $end->toDateString(),
            ]);

        return [
            'keyword_count' => (int) (clone $query)->distinct()->count('gsc_keyword_metrics.query_text'),
            'impressions' => (int) (clone $query)->sum('gsc_keyword_metrics.impressions'),
            'clicks' => (int) (clone $query)->sum('gsc_keyword_metrics.clicks'),
        ];
    }

    private function queryForUser(int $userId): Builder
    {
        return GscKeywordMetric::query()
            ->join('gsc_sites', 'gsc_sites.id', '=', 'gsc_keyword_metrics.site_id')
            ->where('gsc_sites.user_id', $userId);
    }

    private function percentageChange(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current === 0 ? '0%' : '+100%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $rounded = (int) round($change);

        return ($rounded > 0 ? '+' : '').$rounded.'%';
    }

    /**
     * @return array<string, string>
     */
    private function emptyActivity(): array
    {
        return [
            'keyword_count' => '0',
            'keyword_change' => '0%',
            'impressions' => '0',
            'impressions_change' => '0%',
            'clicks' => '0',
            'clicks_change' => '0%',
            'activity_period' => 'No Search Console data yet',
        ];
    }
}
