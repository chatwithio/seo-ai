<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->boolean('auto_publish_enabled')->default(false)->after('content_api_key_hash');
            $table->boolean('auto_publish_multiple_channels')->default(false)->after('auto_publish_enabled');
            $table->unsignedTinyInteger('wordpress_email_priority')->default(10)->after('wordpress_email');
            $table->unsignedTinyInteger('wordpress_webhook_priority')->default(20)->after('wordpress_webhook_secret');
            $table->unsignedTinyInteger('general_webhook_priority')->default(30)->after('general_webhook_secret');

            $table->index('auto_publish_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('publishing_settings', function (Blueprint $table): void {
            $table->dropIndex(['auto_publish_enabled']);
            $table->dropColumn([
                'auto_publish_enabled',
                'auto_publish_multiple_channels',
                'wordpress_email_priority',
                'wordpress_webhook_priority',
                'general_webhook_priority',
            ]);
        });
    }
};
