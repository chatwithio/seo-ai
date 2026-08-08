<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PageContentExtractor
{
    /**
     * @return array{title: string, description: string, headings: array<int, string>, text: string, hash: string}
     */
    public function extract(string $url): array
    {
        $parts = parse_url($url);

        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || blank($parts['host'] ?? null)) {
            throw new RuntimeException('The page URL must be a public HTTP or HTTPS URL.');
        }

        $response = Http::connectTimeout(10)
            ->timeout(30)
            ->withHeaders(['User-Agent' => 'Aigor SEO Content Auditor/1.0'])
            ->get($url);

        $response->throw();

        $html = $response->body();

        if (strlen($html) > 2_000_000) {
            throw new RuntimeException('The page is larger than the 2 MB analysis limit.');
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('The page HTML could not be read.');
        }

        $xpath = new DOMXPath($document);
        $title = trim((string) $xpath->evaluate('string(//title[1])'));
        $description = trim((string) $xpath->evaluate('string(//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]/@content)'));
        $headings = [];

        foreach ($xpath->query('//h1|//h2|//h3') ?: [] as $heading) {
            $value = $this->normalizeText($heading->textContent);

            if ($value !== '') {
                $headings[] = $value;
            }
        }

        foreach (['//script', '//style', '//noscript', '//svg', '//nav', '//footer', '//form'] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $main = $xpath->query('//main[1]')->item(0)
            ?? $xpath->query('//article[1]')->item(0)
            ?? $xpath->query('//body[1]')->item(0);
        $text = $this->normalizeText($main?->textContent ?? '');
        $text = Str::limit($text, 30_000, '');

        if ($text === '') {
            throw new RuntimeException('No readable page content was found.');
        }

        return [
            'title' => Str::limit($title, 255, ''),
            'description' => Str::limit($description, 500, ''),
            'headings' => array_slice(array_values(array_unique($headings)), 0, 40),
            'text' => $text,
            'hash' => hash('sha256', $text),
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
