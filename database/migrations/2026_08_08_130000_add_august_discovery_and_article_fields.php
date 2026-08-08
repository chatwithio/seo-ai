<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('articles_last_viewed_at')->nullable()->after('remember_token')->index();
        });

        DB::table('users')->whereNull('articles_last_viewed_at')->update([
            'articles_last_viewed_at' => now(),
        ]);

        Schema::table('seo_keywords', function (Blueprint $table): void {
            $table->dateTime('discovered_at')->nullable()->after('content_generated_at')->index();
        });

        Schema::table('gsc_sites', function (Blueprint $table): void {
            $table->dateTime('keywords_updated_at')->nullable()->after('last_imported_at')->index();
        });

        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->unsignedBigInteger('site_id')->nullable()->after('user_id')->index();
            $table->unsignedBigInteger('content_improvement_id')->nullable()->after('brief_id')->index();
            $table->text('source_url')->nullable()->after('content_improvement_id');
            $table->unsignedInteger('content_version')->default(1)->after('source_url');
            $table->string('featured_image_disk', 50)->nullable()->after('quality_checks');
            $table->text('featured_image_path')->nullable()->after('featured_image_disk');
            $table->text('featured_image_url')->nullable()->after('featured_image_path');
            $table->string('featured_image_alt', 500)->nullable()->after('featured_image_url');
            $table->text('featured_image_prompt')->nullable()->after('featured_image_alt');
            $table->string('featured_image_status', 30)->default('pending')->after('featured_image_prompt')->index();
            $table->text('featured_image_error')->nullable()->after('featured_image_status');
            $table->dateTime('featured_image_generated_at')->nullable()->after('featured_image_error');
        });

        DB::table('seo_content_drafts')
            ->whereNull('site_id')
            ->whereNotNull('keyword_group_id')
            ->orderBy('id')
            ->chunkById(500, function ($drafts): void {
                foreach ($drafts as $draft) {
                    $siteId = DB::table('seo_keyword_groups')
                        ->where('id', $draft->keyword_group_id)
                        ->value('site_id');

                    if ($siteId) {
                        DB::table('seo_content_drafts')
                            ->where('id', $draft->id)
                            ->update(['site_id' => $siteId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->dropColumn([
                'site_id',
                'content_improvement_id',
                'source_url',
                'content_version',
                'featured_image_disk',
                'featured_image_path',
                'featured_image_url',
                'featured_image_alt',
                'featured_image_prompt',
                'featured_image_status',
                'featured_image_error',
                'featured_image_generated_at',
            ]);
        });

        Schema::table('gsc_sites', function (Blueprint $table): void {
            $table->dropColumn('keywords_updated_at');
        });

        Schema::table('seo_keywords', function (Blueprint $table): void {
            $table->dropColumn('discovered_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('articles_last_viewed_at');
        });
    }
};
