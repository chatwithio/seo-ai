<?php

namespace App\Services;

use App\Models\SeoKeywordGroup;
use App\Models\SeoContentBrief;
use App\Models\SeoContentDraft;
use Illuminate\Support\Str;

class SeoContentGenerationService
{
    public function __construct(
        protected SeoPromptService $promptService,
        protected LlmContentService $llmService
    ) {}

    public function generateBrief(SeoKeywordGroup $group)
    {
        $primaryKeyword = $group->primaryKeyword ? $group->primaryKeyword->query_text : $group->group_name;
        $secondaryKeywords = $group->keywords()->where('role', 'secondary')->pluck('query_text')->implode(', ');
        
        $promptData = $this->promptService->getPrompt('generate_brief', [
            'primary_keyword' => $primaryKeyword,
            'secondary_keywords' => $secondaryKeywords,
            'intent' => $group->group_intent,
            'site_context' => $group->site->name ?? $group->site->site_url,
        ]);

        if (!$promptData) throw new \Exception("Prompt config missing for generate_brief");

        $response = $this->llmService->call($promptData);
        $data = json_decode($response, true);

        if (!$data) throw new \Exception("Failed to decode JSON from LLM: " . $response);

        $brief = SeoContentBrief::updateOrCreate(
            ['keyword_group_id' => $group->id],
            [
                'title' => $data['title'] ?? $primaryKeyword,
                'slug' => Str::slug($data['title'] ?? $primaryKeyword),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'h1' => $data['h1'] ?? null,
                'primary_keyword' => $primaryKeyword,
                'secondary_keywords' => json_encode(array_filter(array_map('trim', explode(',', $secondaryKeywords)))),
                'faq_keywords' => json_encode($data['faq_keywords'] ?? []),
                'search_intent' => $data['search_intent'] ?? $group->group_intent,
                'outline' => json_encode($data['outline'] ?? []),
                'must_answer_questions' => json_encode($data['must_answer_questions'] ?? []),
                'seo_notes' => json_encode(['notes' => $data['seo_notes'] ?? '']),
                'status' => 'draft',
            ]
        );

        $group->update(['status' => 'brief_generated']);

        return $brief;
    }

    public function generateDraft(SeoContentBrief $brief)
    {
        $promptData = $this->promptService->getPrompt('generate_draft', [
            'brief' => json_encode($brief->toArray())
        ]);

        if (!$promptData) throw new \Exception("Prompt config missing for generate_draft");

        $htmlContent = $this->llmService->call($promptData);

        $draft = SeoContentDraft::updateOrCreate(
            ['brief_id' => $brief->id],
            [
                'keyword_group_id' => $brief->keyword_group_id,
                'title' => $brief->title,
                'slug' => $brief->slug,
                'meta_title' => $brief->meta_title,
                'meta_description' => $brief->meta_description,
                'html' => $htmlContent,
                'status' => 'draft',
            ]
        );

        $brief->group->update(['status' => 'draft_generated']);

        return $draft;
    }

    public function reviewDraft(SeoContentDraft $draft)
    {
        $brief = $draft->brief;
        $promptData = $this->promptService->getPrompt('review_content', [
            'brief' => json_encode($brief->toArray()),
            'draft' => $draft->html
        ]);

        if (!$promptData) throw new \Exception("Prompt config missing for review_content");

        $response = $this->llmService->call($promptData);
        $data = json_decode($response, true);

        if (!$data) throw new \Exception("Failed to decode JSON from LLM: " . $response);

        $draft->update([
            'quality_checks' => json_encode([
                'score' => $data['score'] ?? 0,
                'improvements' => $data['improvements'] ?? []
            ]),
            'status' => ($data['is_approved'] ?? false) ? 'approved' : 'needs_review'
        ]);

        return $draft;
    }
}
