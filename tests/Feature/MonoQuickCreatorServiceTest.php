<?php

namespace Tests\Feature;

use App\Services\MonoQuickCreatorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MonoQuickCreatorServiceTest extends TestCase
{
    public function test_it_requires_complete_configuration(): void
    {
        config([
            'services.mono.base_url' => '',
            'services.mono.token' => '',
            'services.mono.template_id' => '',
        ]);

        $this->expectException(RuntimeException::class);
        app(MonoQuickCreatorService::class)->generate([
            'company' => 'Example',
            'business_type' => 'Consulting',
            'services' => ['SEO'],
        ]);
    }

    public function test_it_calls_mono_and_stores_the_example_privately(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://mono.example/api/v1/generate-content' => Http::response([
                'content' => ['headline' => 'Example headline'],
            ]),
        ]);
        config([
            'services.mono.base_url' => 'https://mono.example/api/v1',
            'services.mono.token' => 'private-token',
            'services.mono.template_id' => 'template-1',
        ]);

        $result = app(MonoQuickCreatorService::class)->generate([
            'company' => 'Example',
            'business_type' => 'Consulting',
            'services' => ['SEO'],
            'language' => 'English',
            'tone' => 'Professional',
            'audience' => 'Business owners',
        ]);

        Storage::disk('local')->assertExists($result['path']);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer private-token')
            && $request['template_id'] === 'template-1');
    }
}
