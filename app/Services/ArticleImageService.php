<?php

namespace App\Services;

use App\Models\SeoAuditLog;
use App\Models\SeoContentDraft;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ArticleImageService
{
    public function generate(SeoContentDraft $draft, bool $replace = false): bool
    {
        if (! $replace && $draft->featured_image_status === 'ready' && $draft->featured_image_path) {
            return true;
        }

        $draft->loadMissing('site', 'brief', 'contentImprovement');
        $disk = (string) config('seo_agent.images.disk', 'public');
        $key = config('services.openai.key');

        if (blank($key)) {
            return $this->fail($draft, 'OpenAI API key is missing.');
        }

        $prompt = $this->prompt($draft);
        $draft->update([
            'featured_image_status' => 'generating',
            'featured_image_prompt' => $prompt,
            'featured_image_error' => null,
        ]);

        try {
            $response = Http::withToken($key)
                ->connectTimeout(15)
                ->timeout(180)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => config('seo_agent.images.model', 'gpt-image-2'),
                    'prompt' => $prompt,
                    'size' => config('seo_agent.images.size', '1536x1024'),
                    'quality' => config('seo_agent.images.quality', 'medium'),
                    'output_format' => 'jpeg',
                ]);

            $response->throw();
            $encoded = $response->json('data.0.b64_json');
            $binary = is_string($encoded) ? base64_decode($encoded, true) : false;

            if ($binary === false || strlen($binary) < 100) {
                throw new RuntimeException('The image API returned no usable image.');
            }

            $path = 'seo-articles/'.$draft->user_id.'/'.$draft->id.'-'.Str::uuid().'.jpg';

            if (! Storage::disk($disk)->put($path, $binary)) {
                throw new RuntimeException('The generated image could not be stored.');
            }

            $oldDisk = $draft->featured_image_disk;
            $oldPath = $draft->featured_image_path;

            $draft->update([
                'featured_image_disk' => $disk,
                'featured_image_path' => $path,
                'featured_image_url' => Storage::disk($disk)->url($path),
                'featured_image_alt' => $draft->featured_image_alt ?: Str::limit($draft->title, 250, ''),
                'featured_image_status' => 'ready',
                'featured_image_error' => null,
                'featured_image_generated_at' => now(),
            ]);

            if ($oldPath && ($oldDisk !== $disk || $oldPath !== $path)) {
                Storage::disk($oldDisk ?: $disk)->delete($oldPath);
            }

            return true;
        } catch (Throwable $exception) {
            return $this->fail($draft, $exception->getMessage());
        }
    }

    public function skip(SeoContentDraft $draft): void
    {
        $draft->update([
            'featured_image_status' => 'skipped',
            'featured_image_error' => null,
        ]);
    }

    private function prompt(SeoContentDraft $draft): string
    {
        $keyword = $draft->brief?->primary_keyword
            ?? ($draft->contentImprovement?->target_keywords[0] ?? null)
            ?? $draft->title;
        $summary = Str::limit(strip_tags((string) ($draft->meta_description ?: $draft->plain_text)), 700, '');

        return trim(<<<PROMPT
Create a polished editorial featured image for an SEO article.
Article title: {$draft->title}
Primary topic: {$keyword}
Website: {$draft->site?->site_url}
Language: {$draft->language}
Summary: {$summary}

Use a professional, credible, modern photographic or refined editorial illustration style. Create one clear focal subject with generous composition suitable for a blog header. Do not add words, captions, watermarks, UI screenshots, or invented brand logos.
PROMPT);
    }

    private function fail(SeoContentDraft $draft, string $message): bool
    {
        $draft->update([
            'featured_image_status' => 'failed',
            'featured_image_error' => Str::limit($message, 2000, ''),
        ]);

        SeoAuditLog::create([
            'user_id' => $draft->user_id,
            'site_id' => $draft->site_id,
            'entity_type' => 'article_image',
            'action' => 'generation_failed',
            'message' => $message,
            'context' => ['draft_id' => $draft->id],
        ]);

        return false;
    }
}
