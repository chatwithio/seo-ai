<?php

namespace App\Services;

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
        $this->apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');
    }

    public function call(array $promptData): ?string
    {
        if (!$this->apiKey) {
            throw new \Exception('OpenAI API key is missing.');
        }

        $messages = [];
        if (!empty($promptData['system_prompt'])) {
            $messages[] = ['role' => 'system', 'content' => $promptData['system_prompt']];
        }
        $messages[] = ['role' => 'user', 'content' => $promptData['user_prompt']];

        $payload = [
            'model' => config('seo_agent.llm_model', 'gpt-4o'),
            'messages' => $messages,
            'temperature' => 0.7,
        ];

        if (!empty($promptData['output_format'])) {
            $schema = is_string($promptData['output_format']) ? json_decode($promptData['output_format'], true) : $promptData['output_format'];
            if ($schema) {
                $payload['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'seo_output',
                        'schema' => $schema,
                        'strict' => true,
                    ]
                ];
            }
        }

        try {
            $response = $this->client->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody(), true);
            return $body['choices'][0]['message']['content'] ?? null;
        } catch (\Exception $e) {
            \App\Models\SeoAuditLog::create([
                'entity_type' => 'llm_call',
                'action' => 'llm_failed',
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
