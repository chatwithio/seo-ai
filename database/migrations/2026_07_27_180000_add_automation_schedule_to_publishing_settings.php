<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->string('automation_publish_time', 5)->nullable()->after('auto_publish_multiple_channels');
            $table->string('automation_timezone', 64)->default('UTC')->after('automation_publish_time');
        });
    }

    public function down(): void
    {
        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'automation_publish_time',
                'automation_timezone',
            ]);
        });
    }
};
