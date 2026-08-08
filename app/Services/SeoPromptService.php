<?php

namespace App\Services;

use App\Models\AiPrompt;

class SeoPromptService
{
    public function getPrompt(string $key, array $variables = []): ?array
    {
        $prompt = AiPrompt::where('prompt_key', $key)->where('is_active', true)->first();
        $configured = config("seo_agent_prompts.prompts.{$key}");

        if (! $prompt && ! is_array($configured)) {
            return null;
        }

        $userPrompt = $prompt?->user_prompt ?? $configured['user_prompt'];
        
        foreach ($variables as $k => $v) {
            $userPrompt = str_replace('{' . $k . '}', $v, $userPrompt);
        }

        return [
            'system_prompt' => $prompt?->system_prompt ?? ($configured['system_prompt'] ?? null),
            'user_prompt' => $userPrompt,
            'output_format' => $prompt?->output_format ?? ($configured['output_format'] ?? null),
        ];
    }
}
