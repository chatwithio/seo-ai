<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
    {
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('query_text', 500);
            $table->string('normalized_query', 500);
            $table->char('query_hash', 64);
            $table->string('language', 10)->nullable();
            $table->string('country', 10)->nullable();
            $table->unsignedInteger('total_clicks')->default(0);
            $table->unsignedInteger('total_impressions')->default(0);
            $table->decimal('avg_ctr', 10, 6)->default(0);
            $table->decimal('avg_position', 10, 4)->default(0);
            $table->text('main_page_url')->nullable();
            $table->enum('intent', ['informational', 'commercial', 'transactional', 'navigational', 'local', 'support', 'unknown'])->default('unknown');
            $table->enum('keyword_type', ['primary_candidate', 'secondary_candidate', 'question', 'brand', 'product', 'category', 'problem', 'comparison', 'unknown'])->default('unknown');
            $table->decimal('priority_score', 10, 4)->default(0)->index();
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'query_hash'], 'uniq_site_keyword_hash');
            $table->index(['site_id', 'intent']);
            $table->index(['site_id', 'total_impressions']);
            $table->index(['site_id', 'avg_position']);
        });
        
        DB::statement('ALTER TABLE seo_keywords ADD INDEX idx_query_text (query_text(191))');
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
};
