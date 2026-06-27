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
        Schema::create('seo_keyword_group_keywords', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('keyword_id')->index();
            $table->enum('role', ['primary', 'secondary', 'supporting', 'question', 'faq', 'semantic_variant', 'internal_link_anchor'])->default('secondary');
            $table->decimal('relevance_score', 5, 4)->default(0)->index();
            $table->decimal('priority_score', 10, 4)->default(0);
            $table->text('usage_instruction')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'keyword_id'], 'uniq_group_keyword');
            $table->index(['group_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_group_keywords');
    }
};
