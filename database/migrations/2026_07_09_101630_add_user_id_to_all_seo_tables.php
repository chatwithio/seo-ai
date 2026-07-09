<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'seo_keywords',
            'seo_keyword_groups',
            'seo_content_briefs',
            'seo_content_drafts',
            'seo_audit_logs',
        ];

        foreach ($tables as $t) {
            if (!Schema::hasColumn($t, 'user_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('id');
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                });
            }
        }

        // Backfill existing records
        $firstUser = \App\Models\User::first();
        if ($firstUser) {
            foreach ($tables as $t) {
                if ($t === 'seo_keywords' || $t === 'seo_keyword_groups' || $t === 'seo_audit_logs') {
                    \Illuminate\Support\Facades\DB::statement("
                        UPDATE `{$t}` t 
                        JOIN `gsc_sites` s ON t.`site_id` = s.`id`
                        SET t.`user_id` = s.`user_id`
                        WHERE s.`user_id` IS NOT NULL
                    ");
                } elseif ($t === 'seo_content_briefs' || $t === 'seo_content_drafts') {
                    \Illuminate\Support\Facades\DB::statement("
                        UPDATE `{$t}` t 
                        JOIN `seo_keyword_groups` g ON t.`keyword_group_id` = g.`id`
                        JOIN `gsc_sites` s ON g.`site_id` = s.`id`
                        SET t.`user_id` = s.`user_id`
                        WHERE s.`user_id` IS NOT NULL
                    ");
                }
                
                \Illuminate\Support\Facades\DB::table($t)->whereNull('user_id')->update(['user_id' => $firstUser->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'seo_keywords',
            'seo_keyword_groups',
            'seo_content_briefs',
            'seo_content_drafts',
            'seo_audit_logs',
        ];

        foreach ($tables as $t) {
            if (Schema::hasColumn($t, 'user_id')) {
                Schema::table($t, function (Blueprint $table) {
                    // Try to drop foreign key first, catching any errors if the key constraint name doesn't match
                    try {
                        $table->dropForeign(['user_id']);
                    } catch (\Exception $e) {}
                    
                    $table->dropColumn('user_id');
                });
            }
        }
    }
};
