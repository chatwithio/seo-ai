<?php

namespace App\Services;

use App\Models\SeoAuditLog;
use GuzzleHttp\Client;

class LlmContentService
{
    protected $client;

    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 120,
        ]);
        $this->apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY') ?? env('LLM_API_KEY');
    }

    public function call(array $promptData, ?int $userId = null, ?int $siteId = null): ?string
    {
        if (! $this->apiKey) {
            throw new \Exception('OpenAI API key is missing.');
        }

        $messages = [];
        if (! empty($promptData['system_prompt'])) {
            $messages[] = ['role' => 'system', 'content' => $promptData['system_prompt']];
        }
        $messages[] = ['role' => 'user', 'content' => $promptData['user_prompt']];

        $payload = [
            'model' => config('seo_agent.llm_model', 'gpt-4o'),
            'messages' => $messages,
            'temperature' => 0.7,
        ];

        if (! empty($promptData['output_format'])) {
            $schema = is_string($promptData['output_format']) ? json_decode($promptData['output_format'], true) : $promptData['output_format'];
            if ($schema) {
                $schema = $this->makeSchemaStrict($schema);
                $payload['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'seo_output',
                        'schema' => $schema,
                        'strict' => true,
                    ],
                ];
            }
        }

        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody(), true);

            return $body['choices'][0]['message']['content'] ?? null;
        } catch (\Exception $e) {
            SeoAuditLog::create([
                'user_id' => $userId,
                'site_id' => $siteId,
                'entity_type' => 'llm_call',
                'action' => 'llm_failed',
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function makeSchemaStrict(array $schema): array
    {
        if (isset($schema['type']) && $schema['type'] === 'object') {
            $schema['additionalProperties'] = false;
            if (isset($schema['properties']) && is_array($schema['properties'])) {
                $schema['required'] = array_keys($schema['properties']);
                foreach ($schema['properties'] as $key => $prop) {
                    if (is_array($prop)) {
                        $schema['properties'][$key] = $this->makeSchemaStrict($prop);
                    }
                }
            }
        } elseif (isset($schema['type']) && $schema['type'] === 'array') {
            if (isset($schema['items']) && is_array($schema['items'])) {
                $schema['items'] = $this->makeSchemaStrict($schema['items']);
            }
        }

        return $schema;
    }
}
