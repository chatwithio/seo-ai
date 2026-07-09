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
        Schema::table('seo_content_briefs', function (Blueprint $table) {
            $table->string('search_intent', 255)->nullable()->change();
            $table->string('recommended_action', 255)->nullable()->change();
        });

        Schema::table('seo_keyword_groups', function (Blueprint $table) {
            $table->string('group_intent', 255)->nullable()->change();
            $table->string('content_type', 255)->nullable()->change();
            $table->string('recommended_action', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_content_briefs', function (Blueprint $table) {
            $table->string('search_intent', 100)->nullable()->change();
            $table->string('recommended_action', 100)->nullable()->change();
        });

        Schema::table('seo_keyword_groups', function (Blueprint $table) {
            $table->string('group_intent', 100)->nullable()->change();
            $table->string('content_type', 100)->nullable()->change();
            $table->string('recommended_action', 100)->nullable()->change();
        });
    }
};
