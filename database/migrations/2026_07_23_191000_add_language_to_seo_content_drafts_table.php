<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->string('language', 30)->nullable()->after('meta_description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->dropIndex(['language']);
            $table->dropColumn('language');
        });
    }
};
