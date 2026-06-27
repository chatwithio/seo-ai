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
        Schema::create('gsc_keyword_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->date('report_date');
            $table->string('query_text', 500);
            $table->text('page_url')->nullable();
            $table->string('country', 10)->nullable();
            $table->string('device', 30)->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0)->index();
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('position', 10, 4)->default(0)->index();
            $table->dateTime('imported_at')->nullable();
            $table->timestamps();
            
            $table->index(['site_id', 'report_date']);
        });
        
        DB::statement('ALTER TABLE gsc_keyword_metrics ADD INDEX idx_query (query_text(191))');
        DB::statement('ALTER TABLE gsc_keyword_metrics ADD UNIQUE KEY uniq_gsc_metric (site_id, report_date, query_text(191), page_url(191), country, device)');
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_keyword_metrics');
    }
};
