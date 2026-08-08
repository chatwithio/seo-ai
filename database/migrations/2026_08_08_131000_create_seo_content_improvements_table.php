<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_content_improvements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('site_id')->index();
            $table->char('page_hash', 64);
            $table->text('page_url');
            $table->string('page_title')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('position', 10, 4)->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->dateTime('scanned_at');
            $table->boolean('is_current')->default(true)->index();
            $table->string('status', 30)->default('not_generated')->index();
            $table->longText('suggested_paragraph')->nullable();
            $table->text('rationale')->nullable();
            $table->json('target_keywords')->nullable();
            $table->string('source_content_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('generated_draft_id')->nullable()->index();
            $table->dateTime('recommendation_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'page_hash'], 'uniq_site_content_improvement_page');
            $table->index(['user_id', 'clicks']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_improvements');
    }
};
