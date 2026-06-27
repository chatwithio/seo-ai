<?php
$dir = __DIR__ . '/database/migrations';
$files = scandir($dir);
$migrations = [];
foreach ($files as $f) {
    if (str_ends_with($f, '.php')) {
        foreach (['gsc_sites', 'google_oauth_tokens', 'gsc_keyword_metrics', 'seo_keyword_group_keywords', 'seo_keyword_groups', 'seo_keyword_relationships', 'seo_keywords', 'ai_prompts', 'seo_audit_logs', 'seo_content_briefs', 'seo_content_drafts'] as $table) {
            if (str_contains($f, 'create_' . $table . '_table')) {
                $migrations[$table] = $dir . '/' . $f;
            }
        }
    }
}

$schemas = [
    'gsc_sites' => <<<EOT
    public function up(): void
    {
        Schema::create('gsc_sites', function (Blueprint \$table) {
            \$table->id();
            \$table->string('site_url')->unique();
            \$table->string('name')->nullable();
            \$table->string('permission_level', 100)->nullable();
            \$table->boolean('is_active')->default(1);
            \$table->dateTime('last_imported_at')->nullable();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_sites');
    }
EOT,
    'google_oauth_tokens' => <<<EOT
    public function up(): void
    {
        Schema::create('google_oauth_tokens', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('user_id')->nullable()->index();
            \$table->string('provider', 50)->default('google')->index();
            \$table->longText('access_token');
            \$table->longText('refresh_token')->nullable();
            \$table->dateTime('expires_at')->nullable();
            \$table->text('scope')->nullable();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_oauth_tokens');
    }
EOT,
    'gsc_keyword_metrics' => <<<EOT
    public function up(): void
    {
        Schema::create('gsc_keyword_metrics', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('site_id');
            \$table->date('report_date');
            \$table->string('query_text', 500);
            \$table->text('page_url')->nullable();
            \$table->string('country', 10)->nullable();
            \$table->string('device', 30)->nullable();
            \$table->unsignedInteger('clicks')->default(0);
            \$table->unsignedInteger('impressions')->default(0)->index();
            \$table->decimal('ctr', 10, 6)->default(0);
            \$table->decimal('position', 10, 4)->default(0)->index();
            \$table->dateTime('imported_at')->nullable();
            \$table->timestamps();
            
            \$table->index(['site_id', 'report_date']);
        });
        
        DB::statement('ALTER TABLE gsc_keyword_metrics ADD INDEX idx_query (query_text(191))');
        DB::statement('ALTER TABLE gsc_keyword_metrics ADD UNIQUE KEY uniq_gsc_metric (site_id, report_date, query_text(191), page_url(191), country, device)');
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_keyword_metrics');
    }
EOT,
    'seo_keywords' => <<<EOT
    public function up(): void
    {
        Schema::create('seo_keywords', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('site_id');
            \$table->string('query_text', 500);
            \$table->string('normalized_query', 500);
            \$table->char('query_hash', 64);
            \$table->string('language', 10)->nullable();
            \$table->string('country', 10)->nullable();
            \$table->unsignedInteger('total_clicks')->default(0);
            \$table->unsignedInteger('total_impressions')->default(0);
            \$table->decimal('avg_ctr', 10, 6)->default(0);
            \$table->decimal('avg_position', 10, 4)->default(0);
            \$table->text('main_page_url')->nullable();
            \$table->enum('intent', ['informational', 'commercial', 'transactional', 'navigational', 'local', 'support', 'unknown'])->default('unknown');
            \$table->enum('keyword_type', ['primary_candidate', 'secondary_candidate', 'question', 'brand', 'product', 'category', 'problem', 'comparison', 'unknown'])->default('unknown');
            \$table->decimal('priority_score', 10, 4)->default(0)->index();
            \$table->decimal('ai_confidence', 5, 4)->nullable();
            \$table->timestamps();

            \$table->unique(['site_id', 'query_hash'], 'uniq_site_keyword_hash');
            \$table->index(['site_id', 'intent']);
            \$table->index(['site_id', 'total_impressions']);
            \$table->index(['site_id', 'avg_position']);
        });
        
        DB::statement('ALTER TABLE seo_keywords ADD INDEX idx_query_text (query_text(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
EOT,
    'seo_keyword_groups' => <<<EOT
    public function up(): void
    {
        Schema::create('seo_keyword_groups', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('site_id');
            \$table->string('group_name');
            \$table->string('slug')->nullable();
            \$table->unsignedBigInteger('primary_keyword_id')->nullable()->index();
            \$table->enum('group_intent', ['informational', 'commercial', 'transactional', 'navigational', 'local', 'support', 'mixed', 'unknown'])->default('unknown');
            \$table->enum('content_type', ['blog_article', 'buying_guide', 'category_page_improvement', 'product_page_improvement', 'faq_block', 'comparison_page', 'landing_page', 'support_article', 'no_content_needed'])->default('blog_article');
            \$table->enum('recommended_action', ['create_new_page', 'improve_existing_page', 'rewrite_meta', 'add_faq', 'merge_with_existing_content', 'no_action'])->default('create_new_page');
            \$table->text('target_page_url')->nullable();
            \$table->unsignedInteger('total_clicks')->default(0);
            \$table->unsignedInteger('total_impressions')->default(0);
            \$table->decimal('avg_ctr', 10, 6)->default(0);
            \$table->decimal('avg_position', 10, 4)->default(0);
            \$table->decimal('opportunity_score', 10, 4)->default(0);
            \$table->text('ai_summary')->nullable();
            \$table->decimal('ai_confidence', 5, 4)->nullable();
            \$table->enum('status', ['new', 'review_needed', 'approved', 'brief_generated', 'draft_generated', 'published', 'rejected'])->default('new');
            \$table->timestamps();

            \$table->index(['site_id', 'status']);
            \$table->index(['site_id', 'opportunity_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_groups');
    }
EOT,
    'seo_keyword_group_keywords' => <<<EOT
    public function up(): void
    {
        Schema::create('seo_keyword_group_keywords', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('group_id');
            \$table->unsignedBigInteger('keyword_id')->index();
            \$table->enum('role', ['primary', 'secondary', 'supporting', 'question', 'faq', 'semantic_variant', 'internal_link_anchor'])->default('secondary');
            \$table->decimal('relevance_score', 5, 4)->default(0)->index();
            \$table->decimal('priority_score', 10, 4)->default(0);
            \$table->text('usage_instruction')->nullable();
            \$table->timestamps();

            \$table->unique(['group_id', 'keyword_id'], 'uniq_group_keyword');
            \$table->index(['group_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_group_keywords');
    }
EOT,
    'seo_keyword_relationships' => <<<EOT
    public function up(): void
    {
        Schema::create('seo_keyword_relationships', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('site_id');
            \$table->unsignedBigInteger('keyword_id_a')->index();
            \$table->unsignedBigInteger('keyword_id_b')->index();
            \$table->enum('relationship_type', ['same_intent', 'semantic_variant', 'synonym', 'parent_child', 'subtopic', 'question_answer', 'comparison', 'not_related'])->default('same_intent');
            \$table->decimal('similarity_score', 5, 4)->default(0)->index();
            \$table->decimal('ai_confidence', 5, 4)->nullable();
            \$table->text('reason')->nullable();
            \$table->enum('source', ['llm', 'embedding', 'manual', 'rule'])->default('llm');
            \$table->timestamps();

            \$table->unique(['site_id', 'keyword_id_a', 'keyword_id_b'], 'uniq_keyword_pair');
            \$table->index(['site_id', 'relationship_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_relationships');
    }
EOT,
    'seo_content_briefs' => <<<EOT
    public function up(): void
    {
        Schema::create('seo_content_briefs', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('keyword_group_id')->index();
            \$table->string('title');
            \$table->string('slug')->nullable();
            \$table->string('meta_title')->nullable();
            \$table->string('meta_description', 500)->nullable();
            \$table->string('h1')->nullable();
            \$table->string('primary_keyword', 500);
            \$table->json('secondary_keywords')->nullable();
            \$table->json('faq_keywords')->nullable();
            \$table->string('search_intent', 100)->nullable();
            \$table->string('content_type', 100)->nullable();
            \$table->string('recommended_action', 100)->nullable();
            \$table->json('outline')->nullable();
            \$table->json('internal_link_suggestions')->nullable();
            \$table->json('must_answer_questions')->nullable();
            \$table->json('seo_notes')->nullable();
            \$table->json('quality_warnings')->nullable();
            \$table->enum('status', ['draft', 'approved', 'rejected'])->default('draft')->index();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_briefs');
    }
EOT,
    'seo_content_drafts' => <<<EOT
    public function up(): void
    {
        Schema::create('seo_content_drafts', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('keyword_group_id')->index();
            \$table->unsignedBigInteger('brief_id')->index();
            \$table->string('title');
            \$table->string('slug')->nullable();
            \$table->string('meta_title')->nullable();
            \$table->string('meta_description', 500)->nullable();
            \$table->longText('html');
            \$table->longText('plain_text')->nullable();
            \$table->json('faq')->nullable();
            \$table->json('internal_link_suggestions')->nullable();
            \$table->json('quality_checks')->nullable();
            \$table->enum('status', ['draft', 'needs_review', 'approved', 'published', 'rejected'])->default('draft')->index();
            \$table->text('published_url')->nullable();
            \$table->dateTime('published_at')->nullable();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_drafts');
    }
EOT,
    'ai_prompts' => <<<EOT
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint \$table) {
            \$table->id();
            \$table->string('prompt_key', 100)->unique();
            \$table->string('name');
            \$table->text('description')->nullable();
            \$table->longText('system_prompt')->nullable();
            \$table->longText('user_prompt');
            \$table->json('output_format')->nullable();
            \$table->boolean('is_active')->default(1)->index();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
EOT,
    'seo_audit_logs' => <<<EOT
    public function up(): void
    {
        Schema::create('seo_audit_logs', function (Blueprint \$table) {
            \$table->id();
            \$table->unsignedBigInteger('user_id')->nullable()->index();
            \$table->unsignedBigInteger('site_id')->nullable()->index();
            \$table->string('entity_type', 100);
            \$table->unsignedBigInteger('entity_id')->nullable();
            \$table->string('action', 100)->index();
            \$table->text('message')->nullable();
            \$table->json('context')->nullable();
            \$table->timestamps();
            
            \$table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audit_logs');
    }
EOT
];

foreach ($schemas as $table => $content) {
    if (isset($migrations[$table])) {
        $file = $migrations[$table];
        $original = file_get_contents($file);
        $original = preg_replace('/public function up\(\): void.*?public function down\(\): void.*?}/s', $content, $original);
        if(!str_contains($original, 'use Illuminate\Support\Facades\DB;')) {
            $original = str_replace('use Illuminate\Support\Facades\Schema;', "use Illuminate\Support\Facades\Schema;\nuse Illuminate\Support\Facades\DB;", $original);
        }
        file_put_contents($file, $original);
        echo "Updated \$table\n";
    }
}
