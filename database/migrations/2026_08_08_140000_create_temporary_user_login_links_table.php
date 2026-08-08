<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_user_login_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->char('token_hash', 64)->unique();
            $table->string('redirect_path', 500)->default('/admin');
            $table->string('created_by')->default('console');
            $table->dateTime('expires_at')->index();
            $table->dateTime('used_at')->nullable()->index();
            $table->string('used_ip', 45)->nullable();
            $table->text('used_user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_user_login_links');
    }
};
