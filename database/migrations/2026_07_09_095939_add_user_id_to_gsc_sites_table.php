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
            $table->unsignedBigInteger('user_id')->nullable()->after('google_oauth_token_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Default existing sites to first user
        $firstUser = \App\Models\User::first();
        if ($firstUser) {
            \Illuminate\Support\Facades\DB::table('gsc_sites')->update(['user_id' => $firstUser->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gsc_sites', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
