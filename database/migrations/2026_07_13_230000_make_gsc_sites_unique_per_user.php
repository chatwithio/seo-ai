<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gsc_sites', function (Blueprint $table) {
            $table->dropUnique('gsc_sites_site_url_unique');
            $table->unique(['user_id', 'site_url'], 'gsc_sites_user_site_unique');
        });

        // Repair tenant ownership on existing site-owned records before future
        // console/background writes begin assigning it directly.
        foreach (['seo_keywords', 'seo_keyword_groups', 'seo_audit_logs'] as $table) {
            DB::statement("
                UPDATE `{$table}` child
                JOIN `gsc_sites` site ON site.`id` = child.`site_id`
                SET child.`user_id` = site.`user_id`
                WHERE site.`user_id` IS NOT NULL
                  AND (child.`user_id` IS NULL OR child.`user_id` <> site.`user_id`)
            ");
        }

        foreach (['seo_content_briefs', 'seo_content_drafts'] as $table) {
            DB::statement("
                UPDATE `{$table}` child
                JOIN `seo_keyword_groups` keyword_group ON keyword_group.`id` = child.`keyword_group_id`
                SET child.`user_id` = keyword_group.`user_id`
                WHERE keyword_group.`user_id` IS NOT NULL
                  AND (child.`user_id` IS NULL OR child.`user_id` <> keyword_group.`user_id`)
            ");
        }
    }

    public function down(): void
    {
        Schema::table('gsc_sites', function (Blueprint $table) {
            $table->dropUnique('gsc_sites_user_site_unique');
            $table->unique('site_url');
        });
    }
};
