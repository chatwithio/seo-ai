<?php

namespace App\Console\Commands;

use App\Models\AiPrompt;
use Illuminate\Console\Command;

class SeedAiPrompts extends Command
{
    protected $signature = 'seo:seed-prompts';
    protected $description = 'Seed AI prompts from config into the database';

    public function handle()
    {
        $prompts = config('seo_agent_prompts.prompts', []);

        foreach ($prompts as $key => $data) {
            AiPrompt::updateOrCreate(
                ['prompt_key' => $key],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'system_prompt' => $data['system_prompt'] ?? null,
                    'user_prompt' => $data['user_prompt'],
                    'output_format' => isset($data['output_format']) ? json_encode($data['output_format']) : null,
                    'is_active' => true,
                ]
            );
            $this->info("Prompt seeded: {$key}");
        }

        $this->info('All prompts seeded successfully.');
    }
}
