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
        Schema::create('seo_keyword_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('group_name');
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('primary_keyword_id')->nullable()->index();
            $table->enum('group_intent', ['informational', 'commercial', 'transactional', 'navigational', 'local', 'support', 'mixed', 'unknown'])->default('unknown');
            $table->enum('content_type', ['blog_article', 'buying_guide', 'category_page_improvement', 'product_page_improvement', 'faq_block', 'comparison_page', 'landing_page', 'support_article', 'no_content_needed'])->default('blog_article');
            $table->enum('recommended_action', ['create_new_page', 'improve_existing_page', 'rewrite_meta', 'add_faq', 'merge_with_existing_content', 'no_action'])->default('create_new_page');
            $table->text('target_page_url')->nullable();
            $table->unsignedInteger('total_clicks')->default(0);
            $table->unsignedInteger('total_impressions')->default(0);
            $table->decimal('avg_ctr', 10, 6)->default(0);
            $table->decimal('avg_position', 10, 4)->default(0);
            $table->decimal('opportunity_score', 10, 4)->default(0);
            $table->text('ai_summary')->nullable();
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->enum('status', ['new', 'review_needed', 'approved', 'brief_generated', 'draft_generated', 'published', 'rejected'])->default('new');
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'opportunity_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_groups');
    }
};
