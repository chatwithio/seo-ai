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
        Schema::table('gsc_sites', function (Blueprint $table) {
            $table->boolean('agent_enabled')->default(0);
            $table->enum('agent_strategy', ['low_ctr', 'high_clicks'])->default('low_ctr');
            $table->unsignedInteger('min_impressions')->default(100);
            $table->unsignedInteger('max_clicks')->default(10);
            $table->unsignedInteger('grouping_limit')->default(50);
        });
    }

    public function down(): void
    {
        Schema::table('gsc_sites', function (Blueprint $table) {
            $table->dropColumn(['agent_enabled', 'agent_strategy', 'min_impressions', 'max_clicks', 'grouping_limit']);
        });
    }
};
