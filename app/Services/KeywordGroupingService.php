<?php

namespace App\Services;

use App\Models\GscSite;
use App\Models\SeoKeyword;
use App\Models\SeoKeywordGroup;
use App\Models\SeoKeywordGroupKeyword;
use Illuminate\Support\Str;

class KeywordGroupingService
{
    public function __construct(
        protected SeoPromptService $promptService,
        protected LlmContentService $llmService
    ) {}

    public function groupKeywordsForSite(GscSite $site, int $limit = 50)
    {
        $query = SeoKeyword::where('site_id', $site->id)
            ->whereNotIn('id', function($q) {
                $q->select('keyword_id')->from('seo_keyword_group_keywords');
            });

        $limit = $site->grouping_limit ?: $limit;

        if ($site->agent_strategy === 'low_ctr') {
            $query->where('total_impressions', '>=', $site->min_impressions ?: 100)
                  ->where('total_clicks', '<=', $site->max_clicks ?: 10)
                  ->orderByDesc('total_impressions');
        } else {
            $query->orderByDesc('total_clicks');
        }

        $keywords = $query->limit($limit)->get();

        if ($keywords->isEmpty()) {
            return 0;
        }

        $keywordListText = $keywords->map(function ($k) {
            return "- {$k->query_text} (Clicks: {$k->total_clicks}, Impressions: {$k->total_impressions})";
        })->implode("\n");

        $promptData = $this->promptService->getPrompt('group_keywords', [
            'site_url' => $site->site_url,
            'keywords' => $keywordListText
        ]);

        if (!$promptData) {
            throw new \Exception('Prompt config missing for group_keywords');
        }

        $response = $this->llmService->call($promptData);
        $data = json_decode($response, true);

        if (!isset($data['groups'])) {
            throw new \Exception('Invalid LLM response format: ' . $response);
        }

        $groupsCreated = 0;

        foreach ($data['groups'] as $groupData) {
            // Find primary keyword model
            $primaryModel = $keywords->firstWhere('query_text', $groupData['primary_keyword']);
            
            if (!$primaryModel) {
                // LLM invented a keyword or picked something else, just pick the first from the secondary if available
                $primaryModel = $keywords->firstWhere('query_text', $groupData['secondary_keywords'][0] ?? null);
            }

            if (!$primaryModel) {
                continue; // Skip if we can't find a valid primary keyword from our list
            }

            $group = SeoKeywordGroup::create([
                'site_id' => $site->id,
                'group_name' => $groupData['group_name'] ?? $primaryModel->query_text,
                'slug' => Str::slug($groupData['group_name'] ?? $primaryModel->query_text),
                'primary_keyword_id' => $primaryModel->id,
                'group_intent' => $groupData['group_intent'] ?? 'unknown',
                'content_type' => $groupData['content_type'] ?? 'blog_article',
                'recommended_action' => $groupData['recommended_action'] ?? 'create_new_page',
                'ai_summary' => $groupData['ai_summary'] ?? null,
                'status' => 'new',
            ]);

            // Attach Primary
            SeoKeywordGroupKeyword::create([
                'group_id' => $group->id,
                'keyword_id' => $primaryModel->id,
                'role' => 'primary',
            ]);

            // Attach Secondaries
            if (!empty($groupData['secondary_keywords'])) {
                foreach ($groupData['secondary_keywords'] as $secKeyText) {
                    $secModel = $keywords->firstWhere('query_text', $secKeyText);
                    if ($secModel && $secModel->id !== $primaryModel->id) {
                        SeoKeywordGroupKeyword::create([
                            'group_id' => $group->id,
                            'keyword_id' => $secModel->id,
                            'role' => 'secondary',
                        ]);
                    }
                }
            }
            
            // Recalculate metrics for group
            $this->recalculateGroupMetrics($group);

            $groupsCreated++;
        }

        return $groupsCreated;
    }

    private function recalculateGroupMetrics(SeoKeywordGroup $group)
    {
        $metrics = SeoKeywordGroupKeyword::where('group_id', $group->id)
            ->join('seo_keywords', 'seo_keywords.id', '=', 'seo_keyword_group_keywords.keyword_id')
            ->selectRaw('SUM(total_clicks) as sum_clicks, SUM(total_impressions) as sum_imp, AVG(avg_position) as avg_pos')
            ->first();

        if ($metrics) {
            $ctr = $metrics->sum_imp > 0 ? ($metrics->sum_clicks / $metrics->sum_imp) * 100 : 0;
            $group->update([
                'total_clicks' => $metrics->sum_clicks ?? 0,
                'total_impressions' => $metrics->sum_imp ?? 0,
                'avg_ctr' => $ctr,
                'avg_position' => $metrics->avg_pos ?? 0,
            ]);
        }
    }
}
