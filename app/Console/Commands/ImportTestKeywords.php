<?php

namespace App\Console\Commands;

use App\Models\GscSite;
use App\Models\GscKeywordMetric;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordGroup;
use App\Models\SeoKeywordGroupKeyword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ImportTestKeywords extends Command
{
    protected $signature = 'seo:import-test-keywords';
    protected $description = 'Import test keywords from the provided screenshots for cachimba-planet.com and alzado.org';

    public function handle()
    {
        $this->info("Starting test keyword import...");

        // 1. Cachimba Planet
        $cachimbaSite = GscSite::updateOrCreate(
            ['site_url' => 'https://cachimba-planet.com/'],
            [
                'name' => 'Cachimba Planet',
                'permission_level' => 'siteOwner',
                'is_active' => true,
                'agent_enabled' => true,
                'agent_strategy' => 'high_clicks',
                'min_impressions' => 0,
                'max_clicks' => 999999,
                'grouping_limit' => 50,
            ]
        );

        $this->info("Site created/updated: {$cachimbaSite->site_url} (ID: {$cachimbaSite->id})");

        // Clean up existing data for this site to ensure clean test
        $this->cleanSiteData($cachimbaSite->id);

        $cachimbaKeywords = [
            ['query' => 'cachimba planet', 'clicks' => 1359, 'impressions' => 2334, 'position' => 1.0],
            ['query' => 'cachimba', 'clicks' => 1040, 'impressions' => 74366, 'position' => 3.0],
            ['query' => 'cachimbas', 'clicks' => 465, 'impressions' => 10921, 'position' => 4.0],
            ['query' => 'vaper cachimba', 'clicks' => 126, 'impressions' => 11248, 'position' => 5.0],
            ['query' => 'cachimbas baratas', 'clicks' => 122, 'impressions' => 2980, 'position' => 6.0],
            ['query' => 'cachimbaplanet', 'clicks' => 72, 'impressions' => 102, 'position' => 1.0],
            ['query' => 'tabaco cachimba sin nicotina', 'clicks' => 70, 'impressions' => 332, 'position' => 2.0],
            ['query' => 'shisha', 'clicks' => 45, 'impressions' => 10097, 'position' => 8.0],
            ['query' => 'vaper de cachimba', 'clicks' => 44, 'impressions' => 2664, 'position' => 5.0],
            ['query' => 'helium small se', 'clicks' => 36, 'impressions' => 91, 'position' => 1.0],
        ];

        $this->insertMetrics($cachimbaSite->id, $cachimbaKeywords, 'https://cachimba-planet.com/');

        // 2. Alzado
        $alzadoSite = GscSite::updateOrCreate(
            ['site_url' => 'https://alzado.org/'],
            [
                'name' => 'Alzado',
                'permission_level' => 'siteOwner',
                'is_active' => true,
                'agent_enabled' => true,
                'agent_strategy' => 'high_clicks',
                'min_impressions' => 0,
                'max_clicks' => 999999,
                'grouping_limit' => 50,
            ]
        );

        $this->info("Site created/updated: {$alzadoSite->site_url} (ID: {$alzadoSite->id})");

        $this->cleanSiteData($alzadoSite->id);

        $alzadoKeywords = [
            ['query' => 'verdades universales mercadona', 'clicks' => 16, 'impressions' => 71, 'position' => 1.5],
            ['query' => 'cuanto le cuesta a coca cola hacer una coca cola', 'clicks' => 2, 'impressions' => 44, 'position' => 4.0],
            ['query' => 'apx bbva', 'clicks' => 1, 'impressions' => 4, 'position' => 2.0],
            ['query' => 'farmasi opiniones', 'clicks' => 0, 'impressions' => 383, 'position' => 9.0],
            ['query' => 'precio in vitro ivi', 'clicks' => 0, 'impressions' => 169, 'position' => 8.5],
            ['query' => 'precio ivi', 'clicks' => 0, 'impressions' => 163, 'position' => 8.0],
            ['query' => 'precio fecundacion in vitro ivi', 'clicks' => 0, 'impressions' => 157, 'position' => 9.0],
            ['query' => 'ivi precios', 'clicks' => 0, 'impressions' => 156, 'position' => 8.0],
            ['query' => '- alzado / alzados', 'clicks' => 0, 'impressions' => 123, 'position' => 7.0],
        ];

        $this->insertMetrics($alzadoSite->id, $alzadoKeywords, 'https://alzado.org/');

        // Run aggregation for both sites
        $this->info("Aggregating keywords for Cachimba Planet...");
        Artisan::call('seo:aggregate-keywords', ['site_id' => $cachimbaSite->id], $this->output);

        $this->info("Aggregating keywords for Alzado...");
        Artisan::call('seo:aggregate-keywords', ['site_id' => $alzadoSite->id], $this->output);

        $this->info("Test keywords successfully imported and aggregated!");
        return 0;
    }

    private function cleanSiteData(int $siteId)
    {
        $this->info("Cleaning up existing GSC data for site ID: {$siteId}...");
        
        // Remove keyword group mappings and groups
        $groupIds = SeoKeywordGroup::where('site_id', $siteId)->pluck('id');
        SeoKeywordGroupKeyword::whereIn('group_id', $groupIds)->delete();
        SeoKeywordGroup::where('site_id', $siteId)->delete();

        // Remove SEO keywords and metrics
        SeoKeyword::where('site_id', $siteId)->delete();
        GscKeywordMetric::where('site_id', $siteId)->delete();
    }

    private function insertMetrics(int $siteId, array $keywords, string $pageUrl)
    {
        $reportDate = '2026-07-03';
        
        foreach ($keywords as $kw) {
            $ctr = $kw['impressions'] > 0 ? ($kw['clicks'] / $kw['impressions']) * 100 : 0.0;
            
            GscKeywordMetric::create([
                'site_id' => $siteId,
                'report_date' => $reportDate,
                'query_text' => $kw['query'],
                'page_url' => $pageUrl,
                'country' => 'es',
                'device' => 'desktop',
                'clicks' => $kw['clicks'],
                'impressions' => $kw['impressions'],
                'ctr' => $ctr,
                'position' => $kw['position'],
                'imported_at' => now(),
            ]);
        }
    }
}
