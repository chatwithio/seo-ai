<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->string('wordpress_post_author')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->dropColumn('wordpress_post_author');
        });
    }
};
