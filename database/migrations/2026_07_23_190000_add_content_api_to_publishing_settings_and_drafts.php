<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->boolean('content_api_enabled')->default(false)->after('user_id')->index();
            $table->text('content_api_key')->nullable()->after('content_api_enabled');
            $table->string('content_api_key_hash', 64)->nullable()->unique()->after('content_api_key');
        });

        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->dateTime('api_read_at')->nullable()->after('published_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->dropIndex(['api_read_at']);
            $table->dropColumn('api_read_at');
        });

        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->dropUnique(['content_api_key_hash']);
            $table->dropIndex(['content_api_enabled']);
            $table->dropColumn([
                'content_api_enabled',
                'content_api_key',
                'content_api_key_hash',
            ]);
        });
    }
};
