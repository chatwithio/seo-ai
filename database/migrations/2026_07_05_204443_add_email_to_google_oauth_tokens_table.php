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
        Schema::table('google_oauth_tokens', function (Blueprint $table) {
            $table->string('email')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('google_oauth_tokens', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
