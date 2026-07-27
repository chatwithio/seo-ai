<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_oauth_tokens', function (Blueprint $table): void {
            $table->boolean('is_onboarding')->default(true)->after('scope')->index();
            $table->unsignedTinyInteger('onboarding_step')->default(0)->after('is_onboarding');
            $table->json('onboarding_selected_site_ids')->nullable()->after('onboarding_step');
            $table->dateTime('onboarding_sites_synced_at')->nullable();
            $table->dateTime('onboarding_sites_selected_at')->nullable();
            $table->dateTime('onboarding_keywords_imported_at')->nullable();
            $table->dateTime('onboarding_first_content_at')->nullable();
            $table->dateTime('onboarding_first_content_skipped_at')->nullable();
            $table->dateTime('onboarding_publishing_reviewed_at')->nullable();
            $table->dateTime('onboarding_content_settings_reviewed_at')->nullable();
            $table->dateTime('onboarding_completed_at')->nullable();
        });

        // Do not force established customers back through onboarding.
        DB::table('google_oauth_tokens')->update([
            'is_onboarding' => false,
            'onboarding_step' => 6,
            'onboarding_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('google_oauth_tokens', function (Blueprint $table): void {
            $table->dropIndex(['is_onboarding']);
            $table->dropColumn([
                'is_onboarding',
                'onboarding_step',
                'onboarding_selected_site_ids',
                'onboarding_sites_synced_at',
                'onboarding_sites_selected_at',
                'onboarding_keywords_imported_at',
                'onboarding_first_content_at',
                'onboarding_first_content_skipped_at',
                'onboarding_publishing_reviewed_at',
                'onboarding_content_settings_reviewed_at',
                'onboarding_completed_at',
            ]);
        });
    }
};
