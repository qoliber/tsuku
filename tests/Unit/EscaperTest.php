<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class EscaperTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testHtmlEscapesSpecialCharacters(): void
    {
        $template = '@html(text)';
        $result = $this->tsuku->process($template, [
            'text' => '<script>alert("XSS")</script>',
        ]);

        $this->assertSame('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $result);
    }

    public function testHtmlEscapesQuotes(): void
    {
        $template = '@html(text)';
        $result = $this->tsuku->process($template, [
            'text' => 'He said "Hello" & \'Goodbye\'',
        ]);

        $this->assertSame('He said &quot;Hello&quot; &amp; &apos;Goodbye&apos;', $result);
    }

    public function testXmlEscapesSpecialCharacters(): void
    {
        $template = '@xml(text)';
        $result = $this->tsuku->process($template, [
            'text' => '<node attr="value">Content & more</node>',
        ]);

        $this->assertSame('&lt;node attr=&quot;value&quot;&gt;Content &amp; more&lt;/node&gt;', $result);
    }

    public function testJsonEscapesString(): void
    {
        $template = '@json(text)';
        $result = $this->tsuku->process($template, [
            'text' => 'Hello "World" & <script>',
        ]);

        // JSON encode adds quotes and escapes special chars
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('\u0026', $result); // & is escaped
    }

    public function testUrlEscapesSpecialCharacters(): void
    {
        $template = '@url(text)';
        $result = $this->tsuku->process($template, [
            'text' => 'Hello World & More',
        ]);

        $this->assertSame('Hello%20World%20%26%20More', $result);
    }

    public function testUrlEscapesQueryParameters(): void
    {
        $template = 'https://example.com?query=@url(search)';
        $result = $this->tsuku->process($template, [
            'search' => 'foo bar & baz=123',
        ]);

        $this->assertSame('https://example.com?query=foo%20bar%20%26%20baz%3D123', $result);
    }

    public function testCsvEscapesComma(): void
    {
        $template = '@csv(text)';
        $result = $this->tsuku->process($template, [
            'text' => 'Hello, World',
        ]);

        $this->assertSame('"Hello, World"', $result);
    }

    public function testCsvEscapesQuotes(): void
    {
        $template = '@csv(text)';
        $result = $this->tsuku->process($template, [
            'text' => 'He said "Hello"',
        ]);

        $this->assertSame('"He said ""Hello"""', $result);
    }

    public function testCsvEscapesNewlines(): void
    {
        $template = '@csv(text)';
        $result = $this->tsuku->process($template, [
            'text' => "Line 1\nLine 2",
        ]);

        $this->assertSame("\"Line 1\nLine 2\"", $result);
    }

    public function testCsvNoEscapeSimpleText(): void
    {
        $template = '@csv(text)';
        $result = $this->tsuku->process($template, [
            'text' => 'SimpleText',
        ]);

        $this->assertSame('SimpleText', $result);
    }

    public function testEscapeDefaultsToHtml(): void
    {
        $template = '@escape(text)';
        $result = $this->tsuku->process($template, [
            'text' => '<script>alert("XSS")</script>',
        ]);

        $this->assertSame('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $result);
    }

    public function testEscapeWithHtmlType(): void
    {
        $template = '@escape(text, "html")';
        $result = $this->tsuku->process($template, [
            'text' => '<div>Content</div>',
        ]);

        $this->assertSame('&lt;div&gt;Content&lt;/div&gt;', $result);
    }

    public function testEscapeWithXmlType(): void
    {
        $template = '@escape(text, "xml")';
        $result = $this->tsuku->process($template, [
            'text' => '<node>Value & More</node>',
        ]);

        $this->assertSame('&lt;node&gt;Value &amp; More&lt;/node&gt;', $result);
    }

    public function testEscapeWithUrlType(): void
    {
        $template = '@escape(text, "url")';
        $result = $this->tsuku->process($template, [
            'text' => 'Hello World',
        ]);

        $this->assertSame('Hello%20World', $result);
    }

    public function testEscapeWithCsvType(): void
    {
        $template = '@escape(text, "csv")';
        $result = $this->tsuku->process($template, [
            'text' => 'Hello, World',
        ]);

        $this->assertSame('"Hello, World"', $result);
    }

    public function testHtmlEscapingInTemplate(): void
    {
        $template = '<div class="user-content">@html(user.comment)</div>';
        $result = $this->tsuku->process($template, [
            'user' => ['comment' => '<script>alert("XSS")</script>'],
        ]);

        $this->assertSame(
            '<div class="user-content">&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;</div>',
            $result
        );
    }

    public function testEscapingInLoop(): void
    {
        $template = '@for(items as item)
@html(item)
@end';

        $result = $this->tsuku->process($template, [
            'items' => ['<b>Bold</b>', '<i>Italic</i>'],
        ]);

        $expected = '&lt;b&gt;Bold&lt;/b&gt;
&lt;i&gt;Italic&lt;/i&gt;
';

        $this->assertSame($expected, $result);
    }

    public function testCsvExportWithEscaping(): void
    {
        $template = 'Name,Description
@for(products as product)
@csv(product.name),@csv(product.description)
@end';

        $result = $this->tsuku->process($template, [
            'products' => [
                ['name' => 'Widget', 'description' => 'A simple widget'],
                ['name' => 'Gadget, Premium', 'description' => 'A "premium" gadget'],
            ],
        ]);

        $expected = 'Name,Description
Widget,A simple widget
"Gadget, Premium","A ""premium"" gadget"
';

        $this->assertSame($expected, $result);
    }

    public function testUrlEncodingInLinks(): void
    {
        $template = '@for(items as item)
https://example.com/search?q=@url(item.query)
@end';

        $result = $this->tsuku->process($template, [
            'items' => [
                ['query' => 'foo bar'],
                ['query' => 'test & verify'],
            ],
        ]);

        $expected = 'https://example.com/search?q=foo%20bar
https://example.com/search?q=test%20%26%20verify
';

        $this->assertSame($expected, $result);
    }
}
