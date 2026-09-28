<?php

namespace Tests\Unit;

use App\Models\SeoContentDraft;
use App\Support\ArticleHtml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArticleHtmlTest extends TestCase
{
    public static function examples(): array
    {
        return [
            [" \n```html\n<h1>Jade</h1>\n```\n", '<h1>Jade</h1>'],
            ["```HTML\r\n<p>Jade</p>\r\n```", '<p>Jade</p>'],
            ['<p>```html</p><h1>Jade</h1><p>```</p>', '<h1>Jade</h1>'],
            ["<p>Example</p><pre>```html\ncode\n```</pre>", "<p>Example</p><pre>```html\ncode\n```</pre>"],
            ['<p>Keep `literal` text</p>', '<p>Keep `literal` text</p>'],
        ];
    }

    #[DataProvider('examples')]
    public function test_outer_fences_are_removed_without_damaging_content(string $input, string $expected): void
    {
        $this->assertSame($expected, ArticleHtml::clean($input));
        $this->assertSame($expected, ArticleHtml::clean(ArticleHtml::clean($input)));
    }

    public function test_existing_and_new_model_content_is_clean(): void
    {
        $draft = new SeoContentDraft;
        $draft->setRawAttributes(['html' => "```html\n<p>Old article</p>\n```"]);
        $this->assertSame('<p>Old article</p>', $draft->html);
        $draft->html = "```html\n<p>New article</p>\n```";
        $this->assertSame('<p>New article</p>', $draft->getAttributes()['html']);
    }
}
