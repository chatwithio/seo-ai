<?php

namespace App\Console\Commands;

use App\Models\GscKeywordMetric;
use App\Models\GscSite;
use App\Models\SeoKeyword;
use App\Services\SeoKeywordNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateSeoKeywords extends Command
{
    protected $signature = 'seo:aggregate-keywords {site_id}';

    protected $description = 'Aggregate GSC keyword metrics into SEO Keywords table';

    public function handle(SeoKeywordNormalizer $normalizer)
    {
        $siteId = $this->argument('site_id');
        $site = GscSite::findOrFail($siteId);

        $this->info("Aggregating metrics for site: {$site->site_url}");

        // For simplicity, we just aggregate all metrics. In a real scenario, you'd only aggregate new ones.
        $metrics = GscKeywordMetric::where('site_id', $site->id)
            ->select('query_text', DB::raw('SUM(clicks) as total_clicks'), DB::raw('SUM(impressions) as total_impressions'), DB::raw('AVG(position) as avg_position'))
            ->groupBy('query_text')
            ->cursor();

        $count = 0;
        $records = [];
        $timestamp = now();

        foreach ($metrics as $metric) {
            $normalized = $normalizer->normalize($metric->query_text);
            $hash = $normalizer->hash($normalized);

            $avgCtr = $metric->total_impressions > 0 ? ($metric->total_clicks / $metric->total_impressions) * 100 : 0;

            $records[] = [
                'user_id' => $site->user_id,
                'site_id' => $site->id,
                'query_hash' => $hash,
                'query_text' => $metric->query_text,
                'normalized_query' => $normalized,
                'total_clicks' => $metric->total_clicks,
                'total_impressions' => $metric->total_impressions,
                'avg_ctr' => $avgCtr,
                'avg_position' => $metric->avg_position,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $count++;

            if (count($records) === 1000) {
                $this->upsertKeywords($records);
                $records = [];
                $this->info("Processed {$count} keywords...");
            }
        }

        if ($records !== []) {
            $this->upsertKeywords($records);
        }

        $this->info("Aggregation complete. Processed {$count} keywords.");
    }

    private function upsertKeywords(array $records): void
    {
        SeoKeyword::upsert(
            $records,
            ['site_id', 'query_hash'],
            [
                'user_id',
                'query_text',
                'normalized_query',
                'total_clicks',
                'total_impressions',
                'avg_ctr',
                'avg_position',
                'updated_at',
            ],
        );
    }
}
