<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table): void {
            $table->unsignedTinyInteger('content_generation_status')
                ->default(0)
                ->index()
                ->after('ai_confidence');
            $table->timestamp('content_generated_at')
                ->nullable()
                ->after('content_generation_status');
        });

        $generatedKeywordIds = DB::table('seo_keyword_group_keywords')
            ->join('seo_content_drafts', 'seo_content_drafts.keyword_group_id', '=', 'seo_keyword_group_keywords.group_id')
            ->pluck('seo_keyword_group_keywords.keyword_id');

        $generatedPrimaryKeywordIds = DB::table('seo_keyword_groups')
            ->join('seo_content_drafts', 'seo_content_drafts.keyword_group_id', '=', 'seo_keyword_groups.id')
            ->whereNotNull('seo_keyword_groups.primary_keyword_id')
            ->pluck('seo_keyword_groups.primary_keyword_id');

        DB::table('seo_keywords')
            ->whereIn('id', $generatedKeywordIds->merge($generatedPrimaryKeywordIds)->unique())
            ->update([
                'content_generation_status' => 2,
                'content_generated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table): void {
            $table->dropColumn(['content_generation_status', 'content_generated_at']);
        });
    }
};
