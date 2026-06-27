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
        Schema::create('seo_keyword_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('keyword_id_a')->index();
            $table->unsignedBigInteger('keyword_id_b')->index();
            $table->enum('relationship_type', ['same_intent', 'semantic_variant', 'synonym', 'parent_child', 'subtopic', 'question_answer', 'comparison', 'not_related'])->default('same_intent');
            $table->decimal('similarity_score', 5, 4)->default(0)->index();
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->text('reason')->nullable();
            $table->enum('source', ['llm', 'embedding', 'manual', 'rule'])->default('llm');
            $table->timestamps();

            $table->unique(['site_id', 'keyword_id_a', 'keyword_id_b'], 'uniq_keyword_pair');
            $table->index(['site_id', 'relationship_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_relationships');
    }
};
