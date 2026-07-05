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
            $table->unsignedBigInteger('google_oauth_token_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('gsc_sites', function (Blueprint $table) {
            $table->dropColumn('google_oauth_token_id');
        });
    }
};
