<?php

namespace App\Services;

class SeoKeywordNormalizer
{
    public function normalize(string $keyword): string
    {
        $keyword = mb_strtolower($keyword, 'UTF-8');
        
        // Remove special characters except alphanumeric and spaces
        $keyword = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $keyword);
        
        // Remove extra spaces
        $keyword = preg_replace('/\s+/', ' ', $keyword);
        
        return trim($keyword);
    }

    public function hash(string $normalizedKeyword): string
    {
        return hash('sha256', $normalizedKeyword);
    }
}
