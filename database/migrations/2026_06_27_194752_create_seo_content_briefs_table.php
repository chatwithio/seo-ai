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
        Schema::create('seo_content_briefs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('keyword_group_id')->index();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('h1')->nullable();
            $table->string('primary_keyword', 500);
            $table->json('secondary_keywords')->nullable();
            $table->json('faq_keywords')->nullable();
            $table->string('search_intent', 100)->nullable();
            $table->string('content_type', 100)->nullable();
            $table->string('recommended_action', 100)->nullable();
            $table->json('outline')->nullable();
            $table->json('internal_link_suggestions')->nullable();
            $table->json('must_answer_questions')->nullable();
            $table->json('seo_notes')->nullable();
            $table->json('quality_warnings')->nullable();
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_briefs');
    }
};
