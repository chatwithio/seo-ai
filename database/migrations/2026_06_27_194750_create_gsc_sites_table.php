<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up(): void
    {
        Schema::create('gsc_sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_url')->unique();
            $table->string('name')->nullable();
            $table->string('permission_level', 100)->nullable();
            $table->boolean('is_active')->default(1);
            $table->dateTime('last_imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_sites');
    }
};
