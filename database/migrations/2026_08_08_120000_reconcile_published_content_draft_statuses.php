<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('seo_content_drafts')
            ->whereNotNull('published_at')
            ->whereIn('status', ['draft', 'needs_review', 'approved'])
            ->update([
                'status' => 'published',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Delivery history cannot safely be converted back into review state.
    }
};
