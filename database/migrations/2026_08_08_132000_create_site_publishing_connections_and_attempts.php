<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_publishing_connections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('provider', 50);
            $table->boolean('is_enabled')->default(false)->index();
            $table->unsignedTinyInteger('priority')->default(40);
            $table->longText('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->dateTime('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'provider']);
        });

        Schema::create('content_publication_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->unsignedBigInteger('seo_content_draft_id')->index();
            $table->unsignedInteger('content_version')->default(1);
            $table->string('channel', 50);
            $table->string('status', 30)->default('pending')->index();
            $table->string('request_fingerprint', 64);
            $table->string('external_id')->nullable();
            $table->text('external_url')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('succeeded_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['seo_content_draft_id', 'content_version', 'channel'],
                'uniq_draft_version_channel'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_publication_attempts');
        Schema::dropIfExists('site_publishing_connections');
    }
};
