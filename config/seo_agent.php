<?php

return [
    'llm_model' => env('LLM_MODEL', 'gpt-4o'),
    'import_delay_days' => max(0, (int) env('SEO_IMPORT_DELAY_DAYS', 3)),
    'import_row_limit' => max(1, (int) env('SEO_IMPORT_ROW_LIMIT', 25000)),
    'improvements' => [
        'days' => max(7, (int) env('SEO_IMPROVEMENT_DAYS', 90)),
        'page_limit' => max(1, min(100, (int) env('SEO_IMPROVEMENT_PAGE_LIMIT', 20))),
    ],
    'images' => [
        'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-2'),
        'size' => env('OPENAI_IMAGE_SIZE', '1536x1024'),
        'quality' => env('OPENAI_IMAGE_QUALITY', 'medium'),
        'disk' => env('ARTICLE_IMAGE_DISK', 'public'),
    ],
];
