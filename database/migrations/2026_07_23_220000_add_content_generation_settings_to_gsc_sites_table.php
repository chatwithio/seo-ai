<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gsc_sites', function (Blueprint $table): void {
            $table->boolean('auto_content_enabled')->default(false)->after('grouping_limit');
            $table->string('auto_content_strategy', 40)->default('opportunities')->after('auto_content_enabled');
            $table->unsignedSmallInteger('auto_content_count')->default(3)->after('auto_content_strategy');
            $table->unsignedSmallInteger('auto_content_interval_days')->default(1)->after('auto_content_count');
            $table->string('content_language', 30)->default('English')->after('auto_content_interval_days');
            $table->unsignedSmallInteger('content_length')->default(1000)->after('content_language');
            $table->decimal('content_keyword_density', 3, 1)->default(1.5)->after('content_length');
            $table->text('content_instructions')->nullable()->after('content_keyword_density');
            $table->timestamp('auto_content_last_run_at')->nullable()->after('content_instructions');

            $table->index(['auto_content_enabled', 'auto_content_last_run_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gsc_sites', function (Blueprint $table): void {
            $table->dropIndex(['auto_content_enabled', 'auto_content_last_run_at']);
            $table->dropColumn([
                'auto_content_enabled',
                'auto_content_strategy',
                'auto_content_count',
                'auto_content_interval_days',
                'content_language',
                'content_length',
                'content_keyword_density',
                'content_instructions',
                'auto_content_last_run_at',
            ]);
        });
    }
};
