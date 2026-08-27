<?php

namespace App\Services;

use App\Models\SeoContentDraft;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service for interacting with Mono Quick Creator API (v1).
 *
 * OpenAPI Spec: https://qc-api.yggdrasil.dev-mono.net/api/v1/api-docs/#/
 * Base URL:     https://qc-api.yggdrasil.dev-mono.net/api/v1
 * Auth:         Bearer Token (HAL API validated)
 *
 * Key Endpoints:
 *   POST /generate-content          - AI-powered content generation for templates
 *   POST /sites                     - Queue new site creation using template + content
 *   GET  /jobs/{jobId}              - Track site creation job status
 *   GET  /sites/{siteId}/editor-url - Get editor login ticket and URLs
 *   GET  /templates                 - List available templates
 */
class MonoQuickCreatorService
{
    /**
     * Generate content via Mono Quick Creator /generate-content endpoint.
     *
     * @param  array<string, mixed>  $input
     * @return array{path: string, response: array<string, mixed>}
     */
    public function generate(array $input): array
    {
        $baseUrl = rtrim((string) config('services.mono.base_url', 'https://qc-api.yggdrasil.dev-mono.net/api/v1'), '/');
        $token = (string) config('services.mono.token');
        $templateId = (int) ($input['template_id'] ?? $input['templateId'] ?? config('services.mono.template_id'));

        if ($baseUrl === '' || $token === '' || $templateId <= 0) {
            throw new RuntimeException('Mono API base URL, token, and template ID must be configured.');
        }

        // Build conformant GenerateContentPayload schema
        $companyName = (string) ($input['company'] ?? $input['companyName'] ?? '');
        $businessType = (string) ($input['business_type'] ?? $input['businessType'] ?? 'generalContractor');
        $services = is_array($input['services'] ?? null)
            ? implode(', ', $input['services'])
            : (string) ($input['services'] ?? '');
        $descriptionShort = (string) ($input['description_short'] ?? $input['descriptionShort'] ?? $input['description'] ?? $companyName);
        $siteLanguage = strtoupper((string) ($input['site_language'] ?? $input['siteLanguage'] ?? $this->normalizeLanguageCode($input['language'] ?? 'EN')));

        $globalData = array_filter([
            'companyName' => $companyName,
            'descriptionShort' => $descriptionShort,
            'services' => $services,
            'businessType' => $businessType,
            'siteLanguage' => $siteLanguage,
            'descriptionLong' => $input['description_long'] ?? $input['descriptionLong'] ?? null,
            'mission' => $input['mission'] ?? null,
            'email' => $input['email'] ?? null,
            'phone' => $input['phone'] ?? null,
            'address' => $input['address'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $payload = [
            'templateId' => $templateId,
            'globalData' => $globalData,
        ];

        $tone = $this->normalizeTone($input['tone'] ?? $input['toneOfVoice'] ?? null);
        $audience = $this->normalizeAudience($input['audience'] ?? $input['targetAudience'] ?? null);

        if ($tone || $audience) {
            $payload['aiOptions'] = array_filter([
                'toneOfVoice' => $tone,
                'targetAudience' => $audience,
            ]);
        }

        if (! empty($input['additionalLocales'])) {
            $payload['additionalLocales'] = (array) $input['additionalLocales'];
        }

        Log::info('[MonoQuickCreator] Calling /generate-content', [
            'template_id' => $templateId,
            'company' => $companyName,
            'language' => $siteLanguage,
        ]);

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(120)
            ->post($baseUrl.'/generate-content', $payload);

        if (! $response->successful()) {
            $errorMsg = $response->json('message') ?? $response->body();
            throw new RuntimeException("Mono content generation failed (HTTP {$response->status()}): {$errorMsg}");
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Mono returned an invalid JSON response.');
        }

        $path = 'mono-examples/'.now()->format('Ymd-His').'-'.Str::random(8).'.json';
        Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ['path' => $path, 'response' => $data];
    }

    /**
     * Generate content for a Mono template based on an SeoContentDraft.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{path: string, response: array<string, mixed>}
     */
    public function generateFromDraft(SeoContentDraft $draft, array $overrides = []): array
    {
        $site = $draft->site;
        $companyName = $site?->name ?: parse_url((string) ($site?->site_url ?? 'https://example.com'), PHP_URL_HOST) ?: 'My Company';

        $input = array_merge([
            'company' => $companyName,
            'business_type' => $overrides['business_type'] ?? 'localBusiness',
            'services' => $draft->title,
            'description_short' => (string) ($draft->meta_description ?: Str::limit(strip_tags((string) $draft->html), 200)),
            'language' => $draft->language ?: 'EN',
            'tone' => 'Professional',
            'audience' => 'B2B',
        ], $overrides);

        return $this->generate($input);
    }

    /**
     * Creates a new site using Mono Quick Creator /sites endpoint.
     *
     * @param  array<string, mixed>  $sitePayload
     * @return array{jobId: int, status: string}
     */
    public function createSite(array $sitePayload): array
    {
        $baseUrl = rtrim((string) config('services.mono.base_url', 'https://qc-api.yggdrasil.dev-mono.net/api/v1'), '/');
        $token = (string) config('services.mono.token');

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Mono API base URL and token must be configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(60)
            ->post($baseUrl.'/sites', $sitePayload);

        if (! $response->successful()) {
            $errorMsg = $response->json('message') ?? $response->body();
            throw new RuntimeException("Mono site creation failed (HTTP {$response->status()}): {$errorMsg}");
        }

        $data = $response->json('data') ?? $response->json();

        return [
            'jobId' => (int) ($data['jobId'] ?? 0),
            'status' => (string) ($data['status'] ?? 'queued'),
        ];
    }

    /**
     * Get job status for an asynchronous site creation job.
     *
     * @return array<string, mixed>
     */
    public function getJobStatus(int $jobId): array
    {
        $baseUrl = rtrim((string) config('services.mono.base_url', 'https://qc-api.yggdrasil.dev-mono.net/api/v1'), '/');
        $token = (string) config('services.mono.token');

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(30)
            ->get($baseUrl.'/jobs/'.$jobId);

        if (! $response->successful()) {
            throw new RuntimeException("Mono job query failed (HTTP {$response->status()}): ".$response->body());
        }

        return (array) ($response->json('data') ?? $response->json());
    }

    /**
     * Get editor login URL for a site.
     */
    public function getSiteEditorUrl(int $siteId): string
    {
        $baseUrl = rtrim((string) config('services.mono.base_url', 'https://qc-api.yggdrasil.dev-mono.net/api/v1'), '/');
        $token = (string) config('services.mono.token');

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(30)
            ->get($baseUrl.'/sites/'.$siteId.'/editor-url');

        if (! $response->successful()) {
            throw new RuntimeException("Mono editor URL query failed (HTTP {$response->status()}): ".$response->body());
        }

        return (string) ($response->json('data.loginUrl') ?? $response->json('loginUrl') ?? '');
    }

    private function normalizeLanguageCode(?string $lang): string
    {
        $code = strtoupper(trim((string) $lang));

        return match ($code) {
            'DANISH', 'DA' => 'DA',
            'GERMAN', 'DE' => 'DE',
            'SPANISH', 'ES' => 'ES',
            'FRENCH', 'FR' => 'FR',
            'DUTCH', 'NL' => 'NL',
            'SWEDISH', 'SV' => 'SV',
            'NORWEGIAN', 'NO' => 'NO',
            'ITALIAN', 'IT' => 'IT',
            'PORTUGUESE', 'PT' => 'PT',
            'POLISH', 'PL' => 'PL',
            default => 'EN',
        };
    }

    private function normalizeTone(?string $tone): ?string
    {
        if (blank($tone)) {
            return null;
        }

        $tones = ['Professional', 'Friendly', 'Direct', 'Trust', 'Expert', 'Premium', 'Local', 'Creative'];
        foreach ($tones as $t) {
            if (strcasecmp($tone, $t) === 0) {
                return $t;
            }
        }

        return 'Professional';
    }

    private function normalizeAudience(?string $audience): ?string
    {
        if (blank($audience)) {
            return null;
        }

        $audiences = ['Local', 'Families', 'Value', 'Premium', 'Urgent', 'B2B', 'Eco', 'Trendy'];
        foreach ($audiences as $a) {
            if (strcasecmp($audience, $a) === 0) {
                return $a;
            }
        }

        return 'B2B';
    }
}

