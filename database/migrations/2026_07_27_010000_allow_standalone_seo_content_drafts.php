<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->unsignedBigInteger('keyword_group_id')->nullable()->change();
            $table->unsignedBigInteger('brief_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seo_content_drafts', function (Blueprint $table): void {
            $table->unsignedBigInteger('keyword_group_id')->nullable(false)->change();
            $table->unsignedBigInteger('brief_id')->nullable(false)->change();
        });
    }
};
