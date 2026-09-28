<?php

namespace App\Support;

final class ArticleHtml
{
    public static function clean(string $html): string
    {
        $html = trim($html);

        // Remove only an outer HTML fence, including paragraph wrappers from
        // rich text editors. Preserve backticks and code samples inside articles.
        $opening = '/\A(?:<p>\s*)?```(?:html)?[\t ]*(?:<\/p>|<br\s*\/?\s*>|\R)/i';
        if (preg_match($opening, $html)) {
            $html = preg_replace($opening, '', $html, 1);
            $html = preg_replace('/(?:\R|<p>)\s*```[\t ]*(?:<\/p>)?\s*\z/i', '', $html, 1);
        }

        return trim($html);
    }
}
