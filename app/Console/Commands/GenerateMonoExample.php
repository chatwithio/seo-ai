<?php

namespace App\Console\Commands;

use App\Services\MonoQuickCreatorService;
use Illuminate\Console\Command;

class GenerateMonoExample extends Command
{
    protected $signature = 'seo:mono-generate-example
        {company : Company name}
        {business_type : Type of business}
        {services : Comma-separated services}
        {--language=English}
        {--tone=Professional}
        {--audience=General customers}';

    protected $description = 'Generate and privately store one Mono Quick Creator example';

    public function handle(MonoQuickCreatorService $mono): int
    {
        try {
            $result = $mono->generate([
                'company' => $this->argument('company'),
                'business_type' => $this->argument('business_type'),
                'services' => array_values(array_filter(array_map('trim', explode(',', $this->argument('services'))))),
                'language' => $this->option('language'),
                'tone' => $this->option('tone'),
                'audience' => $this->option('audience'),
            ]);

            $this->info('Mono example generated and stored privately at storage/app/private/'.$result['path']);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
