<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MonoQuickCreatorService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{path: string, response: array<string, mixed>}
     */
    public function generate(array $input): array
    {
        $baseUrl = rtrim((string) config('services.mono.base_url'), '/');
        $token = (string) config('services.mono.token');
        $templateId = (string) config('services.mono.template_id');

        if ($baseUrl === '' || $token === '' || $templateId === '') {
            throw new RuntimeException('Mono API base URL, token, and template ID must be configured.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(15)
            ->timeout(120)
            ->post($baseUrl.'/generate-content', [
                'template_id' => $templateId,
                'company' => $input['company'],
                'business_type' => $input['business_type'],
                'services' => $input['services'],
                'language' => $input['language'] ?? 'English',
                'tone' => $input['tone'] ?? 'Professional',
                'audience' => $input['audience'] ?? 'General customers',
            ]);

        $response->throw();
        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Mono returned an invalid JSON response.');
        }

        $path = 'mono-examples/'.now()->format('Ymd-His').'-'.Str::random(8).'.json';
        Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ['path' => $path, 'response' => $data];
    }
}
