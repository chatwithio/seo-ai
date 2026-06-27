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
        Schema::create('seo_content_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('keyword_group_id')->index();
            $table->unsignedBigInteger('brief_id')->index();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->longText('html');
            $table->longText('plain_text')->nullable();
            $table->json('faq')->nullable();
            $table->json('internal_link_suggestions')->nullable();
            $table->json('quality_checks')->nullable();
            $table->enum('status', ['draft', 'needs_review', 'approved', 'published', 'rejected'])->default('draft')->index();
            $table->text('published_url')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_drafts');
    }
};
