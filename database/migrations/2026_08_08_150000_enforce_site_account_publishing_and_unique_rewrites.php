<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateImprovementIds = DB::table('seo_content_drafts')
            ->whereNotNull('content_improvement_id')
            ->select('content_improvement_id')
            ->groupBy('content_improvement_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('content_improvement_id');

        foreach ($duplicateImprovementIds as $improvementId) {
            $preferredDraftId = DB::table('seo_content_improvements')
                ->where('id', $improvementId)
                ->value('generated_draft_id');
            $preferredDraftId ??= DB::table('seo_content_drafts')
                ->where('content_improvement_id', $improvementId)
                ->oldest('id')
                ->value('id');

            DB::table('seo_content_drafts')
                ->where('content_improvement_id', $improvementId)
                ->where('id', '!=', $preferredDraftId)
                ->update(['content_improvement_id' => null]);
        }

        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->unique('content_improvement_id', 'uniq_content_improvement_draft');
        });

        $duplicateWixAccounts = DB::table('site_publishing_connections')
            ->where('provider', 'wix')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($duplicateWixAccounts as $userId) {
            $connections = DB::table('site_publishing_connections')
                ->where('user_id', $userId)
                ->where('provider', 'wix')
                ->orderByDesc('is_enabled')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            foreach ($connections->skip(1) as $connection) {
                DB::table('site_publishing_connections')
                    ->where('id', $connection->id)
                    ->update(['provider' => 'wix_legacy_'.$connection->id]);
            }
        }

        Schema::table('site_publishing_connections', function (Blueprint $table): void {
            $table->dropUnique('site_publishing_connections_site_id_provider_unique');
            $table->unique(
                ['user_id', 'provider'],
                'uniq_user_publishing_provider',
            );
        });
    }

    public function down(): void
    {
        Schema::table('site_publishing_connections', function (Blueprint $table): void {
            $table->dropUnique('uniq_user_publishing_provider');
            $table->unique(['site_id', 'provider']);
        });

        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->dropUnique('uniq_content_improvement_draft');
        });
    }
};
