<?php

namespace App\Services;

use App\Models\SeoContentBrief;
use App\Models\SeoContentDraft;
use App\Models\SeoKeywordGroup;
use Illuminate\Support\Str;

class SeoContentGenerationService
{
    public function __construct(
        protected SeoPromptService $promptService,
        protected LlmContentService $llmService
    ) {}

    public function generateBrief(SeoKeywordGroup $group, array $options = [])
    {
        $language = $this->normalizeLanguage($options['language'] ?? 'English');
        $primaryKeyword = $group->primaryKeyword ? $group->primaryKeyword->query_text : $group->group_name;
        $secondaryKeywords = $group->keywords()->where('role', 'secondary')->pluck('query_text')->implode(', ');

        $promptData = $this->promptService->getPrompt('generate_brief', [
            'primary_keyword' => $primaryKeyword,
            'secondary_keywords' => $secondaryKeywords,
            'intent' => $group->group_intent,
            'site_context' => $group->site->name ?? $group->site->site_url,
        ]);

        if (! $promptData) {
            throw new \Exception('Prompt config missing for generate_brief');
        }

        $promptData = $this->enforceLanguage($promptData, $language);

        $response = $this->llmService->call($promptData, $group->user_id, $group->site_id);
        $data = json_decode($response, true);

        if (! $data) {
            throw new \Exception('Failed to decode JSON from LLM: '.$response);
        }

        $brief = SeoContentBrief::updateOrCreate(
            ['keyword_group_id' => $group->id],
            [
                'user_id' => $group->user_id,
                'title' => $data['title'] ?? $primaryKeyword,
                'slug' => Str::slug($data['title'] ?? $primaryKeyword),
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'h1' => $data['h1'] ?? null,
                'primary_keyword' => $primaryKeyword,
                'secondary_keywords' => json_encode(array_filter(array_map('trim', explode(',', $secondaryKeywords)))),
                'faq_keywords' => json_encode($data['faq_keywords'] ?? []),
                'search_intent' => Str::limit($data['search_intent'] ?? $group->group_intent, 250, ''),
                'outline' => json_encode($data['outline'] ?? []),
                'must_answer_questions' => json_encode($data['must_answer_questions'] ?? []),
                'seo_notes' => json_encode(['notes' => $data['seo_notes'] ?? '']),
                'status' => 'draft',
            ]
        );

        $group->update(['status' => 'brief_generated']);

        return $brief;
    }

    public function generateDraft(SeoContentBrief $brief, array $options = [])
    {
        $language = $this->normalizeLanguage($options['language'] ?? 'English');
        $promptData = $this->promptService->getPrompt('generate_draft', [
            'brief' => json_encode($brief->toArray()),
            'density' => $options['density'] ?? '1.5',
            'length' => $options['length'] ?? '1000',
            'hint' => $options['hint'] ?? 'None',
            'language' => $language,
        ]);

        if (! $promptData) {
            throw new \Exception('Prompt config missing for generate_draft');
        }

        $promptData = $this->enforceLanguage($promptData, $language);

        $htmlContent = $this->llmService->call(
            $promptData,
            $brief->user_id,
            $brief->group?->site_id,
        );

        if (blank($htmlContent)) {
            throw new \Exception('The AI returned empty article content.');
        }

        $htmlContent = $this->cleanHtmlResponse($htmlContent);

        if ($language === 'Spanish' && ! $this->looksSpanish($htmlContent)) {
            $retryPrompt = $promptData;
            $retryPrompt['user_prompt'] = <<<PROMPT
The previous article was not written fully in Spanish.

Rewrite the entire article below in natural Spanish. Translate every heading, paragraph, list item, callout, and FAQ. Keep the same semantic HTML structure and SEO meaning. Do not include English explanatory text.

ARTICLE TO REWRITE:
{$htmlContent}
PROMPT;

            $htmlContent = $this->llmService->call(
                $retryPrompt,
                $brief->user_id,
                $brief->group?->site_id,
            );

            if (blank($htmlContent) || ! $this->looksSpanish($htmlContent)) {
                throw new \Exception('The AI did not return the article in Spanish. Nothing was saved.');
            }

            $htmlContent = $this->cleanHtmlResponse($htmlContent);
        }

        // Strip markdown code fences if returned by the LLM
        if (str_starts_with($htmlContent, '```')) {
            $htmlContent = preg_replace('/^```(?:html)?\s*/i', '', $htmlContent);
            $htmlContent = preg_replace('/```$/', '', $htmlContent);
            $htmlContent = trim($htmlContent);
        }

        $draft = SeoContentDraft::updateOrCreate(
            ['brief_id' => $brief->id],
            [
                'user_id' => $brief->user_id,
                'keyword_group_id' => $brief->keyword_group_id,
                'title' => $brief->title,
                'slug' => $brief->slug,
                'meta_title' => $brief->meta_title,
                'meta_description' => $brief->meta_description,
                'html' => $htmlContent,
                'plain_text' => trim(strip_tags($htmlContent)),
                'language' => $language,
                'status' => 'draft',
            ]
        );

        $brief->group->update(['status' => 'draft_generated']);

        return $draft;
    }

    private function normalizeLanguage(string $language): string
    {
        return match (Str::lower(trim($language))) {
            'spanish', 'español', 'es' => 'Spanish',
            'french', 'français', 'fr' => 'French',
            'italian', 'italiano', 'it' => 'Italian',
            'german', 'deutsch', 'de' => 'German',
            'portuguese', 'português', 'pt' => 'Portuguese',
            default => 'English',
        };
    }

    /**
     * @param  array{system_prompt?: ?string, user_prompt: string, output_format?: mixed}  $promptData
     * @return array{system_prompt?: ?string, user_prompt: string, output_format?: mixed}
     */
    private function enforceLanguage(array $promptData, string $language): array
    {
        $instruction = match ($language) {
            'Spanish' => 'MANDATORY LANGUAGE: Write every user-visible word in natural Spanish. Translate English source material, headings, metadata, examples, and FAQs into Spanish. Do not answer in English.',
            'French' => 'MANDATORY LANGUAGE: Write every user-visible word in natural French. Translate source material, headings, metadata, examples, and FAQs into French.',
            'Italian' => 'MANDATORY LANGUAGE: Write every user-visible word in natural Italian. Translate source material, headings, metadata, examples, and FAQs into Italian.',
            'German' => 'MANDATORY LANGUAGE: Write every user-visible word in natural German. Translate source material, headings, metadata, examples, and FAQs into German.',
            'Portuguese' => 'MANDATORY LANGUAGE: Write every user-visible word in natural Portuguese. Translate source material, headings, metadata, examples, and FAQs into Portuguese.',
            default => 'MANDATORY LANGUAGE: Write every user-visible word in natural English.',
        };

        $promptData['system_prompt'] = trim(($promptData['system_prompt'] ?? '')."\n\n".$instruction);
        $promptData['user_prompt'] = $instruction."\n\n".$promptData['user_prompt'];

        return $promptData;
    }

    private function cleanHtmlResponse(string $html): string
    {
        $html = trim($html);

        if (str_starts_with($html, '```')) {
            $html = preg_replace('/^```(?:html)?\s*/i', '', $html);
            $html = preg_replace('/```$/', '', $html);
        }

        return trim($html);
    }

    private function looksSpanish(string $html): bool
    {
        $text = Str::lower(strip_tags($html));
        $wordCount = str_word_count($text);

        if ($wordCount < 80) {
            return true;
        }

        preg_match_all('/\b(?:el|la|los|las|de|del|que|en|para|con|una|por|como|su|es|se|al|más)\b/u', $text, $spanish);
        preg_match_all('/\b(?:the|and|of|to|in|for|with|is|that|this|from|your|you|are)\b/u', $text, $english);

        return count($spanish[0]) >= max(5, count($english[0]));
    }

    public function reviewDraft(SeoContentDraft $draft)
    {
        $brief = $draft->brief;
        $promptData = $this->promptService->getPrompt('review_content', [
            'brief' => json_encode($brief->toArray()),
            'draft' => $draft->html,
        ]);

        if (! $promptData) {
            throw new \Exception('Prompt config missing for review_content');
        }

        $response = $this->llmService->call(
            $promptData,
            $draft->user_id,
            $draft->group?->site_id,
        );
        $data = json_decode($response, true);

        if (! $data) {
            throw new \Exception('Failed to decode JSON from LLM: '.$response);
        }

        $draft->update([
            'quality_checks' => json_encode([
                'score' => $data['score'] ?? 0,
                'improvements' => $data['improvements'] ?? [],
            ]),
            'status' => ($data['is_approved'] ?? false) ? 'approved' : 'needs_review',
        ]);

        return $draft;
    }
}
