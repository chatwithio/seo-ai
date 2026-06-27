<?php

namespace App\Services;

use App\Models\AiPrompt;

class SeoPromptService
{
    public function getPrompt(string $key, array $variables = []): ?array
    {
        $prompt = AiPrompt::where('prompt_key', $key)->where('is_active', true)->first();

        if (!$prompt) {
            return null;
        }

        $userPrompt = $prompt->user_prompt;
        
        foreach ($variables as $k => $v) {
            $userPrompt = str_replace('{' . $k . '}', $v, $userPrompt);
        }

        return [
            'system_prompt' => $prompt->system_prompt,
            'user_prompt' => $userPrompt,
            'output_format' => $prompt->output_format,
        ];
    }
}
