<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishing_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('general_webhook_enabled')->default(false);
            $table->text('general_webhook_url')->nullable();
            $table->text('general_webhook_secret')->nullable();
            $table->boolean('wordpress_webhook_enabled')->default(false);
            $table->text('wordpress_webhook_url')->nullable();
            $table->text('wordpress_webhook_secret')->nullable();
            $table->boolean('wordpress_email_enabled')->default(false);
            $table->string('wordpress_email')->nullable();
            $table->enum('wordpress_post_status', ['draft', 'publish'])->default('publish');
            $table->boolean('weekly_activity_email_enabled')->default(true);
            $table->boolean('weekly_ideas_email_enabled')->default(true);
            $table->timestamps();

            $table->index('general_webhook_enabled');
            $table->index('wordpress_webhook_enabled');
            $table->index('wordpress_email_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publishing_settings');
    }
};
