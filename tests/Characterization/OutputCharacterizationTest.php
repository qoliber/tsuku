<?php

/**
 * Tsuku - Transform data into any format
 *
 * @package   Qoliber\Tsuku
 * @author    Jakub Winkler <jwinkler@qoliber.com>
 * @copyright 2025 qoliber
 * @license   MIT
 */

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Characterization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\ParseException;
use Qoliber\Tsuku\Exception\TsukuException;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Tsuku;

/**
 * Output of 1.3.0 that 1.4.0 must reproduce byte for byte. Every expectation was
 * recorded by running the template through the unmodified v1.3.0 code.
 */
final class OutputCharacterizationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function data(): array
    {
        return [
            'x' => true,
            'f' => false,
            'name' => 'N',
            'num' => 5,
            'items' => ['a', 'b'],
            'pad' => '  abc ',
            'withcomma' => 'a,b',
            'nested' => ['a' => ['b' => '!']],
            'c' => 'red',
            's' => 'a',
            'rows' => [['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]],
            'p' => ['tags' => ['t1', 't2'], 'sku' => 'A'],
        ];
    }

    /**
     * Layout that 1.3.0 already got right: unindented directive lines, inline
     * single-line blocks, content lines inside blocks, @for bodies.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function whitespace(): array
    {
        return [
            'standalone if' => ["@if(x)\nyes\n@end\nafter", "yes\nafter"],
            'standalone for' => ["@for(items as i)\n{i}\n@end\n", "a\nb\n"],
            'single-line for at end of input' => ['@for(items as i){i},@end', 'a,b,'],
            'standalone if/else true' => ["before\n@if(x)\nyes\n@else\nno\n@end\nafter", "before\nyes\nafter"],
            'standalone if/else false' => ["before\n@if(f)\nyes\n@else\nno\n@end\nafter", "before\nno\nafter"],
            'standalone match' => [
                "@match(c)\n@case(\"red\")\nR\n@case(\"blue\")\nB\n@default\nD\n@end\n",
                "R\n",
            ],
            'single-line match' => ['@match(c) @case("red") R @default D @end', ' R '],
            'inline if with standalone else/end (true)' => ["{name}: @if(x)\nyes\n@else\nno\n@end\n", "N: yes\n"],
            'inline if with standalone else/end (false)' => ["{name}: @if(f)\nyes\n@else\nno\n@end\n", "N: no\n"],
            'inline match with content cases' => [
                "Status: @match(s)\n@case(\"a\") Active\n@default Unknown\n@end\n",
                "Status:  Active\n",
            ],
            'for with content row' => ["@for(rows as r){r.a},{r.b}\n@end", "1,2\n3,4\n"],
            'if with content on opener line' => ["@if(x)Header\n@end", "Header\n"],
            'blank lines in text' => ["line1\n\n\nline2\n", "line1\n\n\nline2\n"],
            'for at end of input' => ["@for(items as i)\n- {i}\n@end", "- a\n- b\n"],
            'nested standalone' => ["@for(items as i)\n@if(x)\n{i}\n@end\n@end\n", "a\nb\n"],
            'inline if between variables' => ['{name}@if(x)Y@end{name}', 'NYN'],
            'one-line if/else true' => ['@if(x)Y@else N@end', 'Y'],
            'one-line if/else false' => ['@if(f)Y@else N@end', ' N'],
            'inline for with multi-line body' => [
                "Items: @for(items as item)\n- {item}\n@end\nnext",
                "Items: - a\n- b\nnext",
            ],
            'for body verbatim, end followed by text' => [
                "<tags>@for(p.tags as t)\n  <tag>{t}</tag>\n@end</tags>",
                "<tags>  <tag>t1</tag>\n  <tag>t2</tag>\n</tags>",
            ],
            'else at end of content line (true)' => ["@if(x)yes@else\nno\n@end\n", 'yes'],
            'else at end of content line (false)' => ["@if(f)yes@else\nno\n@end\n", "no\n"],
            'for with content after opener, end before text' => [
                "<feed>\n@for(items as i)<item>{i}</item>\n@end</feed>",
                "<feed>\n<item>a</item>\n<item>b</item>\n</feed>",
            ],
            'unless' => ["@unless(f)\nshown\n@end\n", "shown\n"],
            'for without trailing newline' => ["@for(items as i)\n{i}\n@end", "a\nb\n"],
            'blank lines inside body' => ["@if(x)\n\nyes\n\n@end\nafter", "\nyes\n\nafter"],
            'text then standalone if at end' => ["x\n@if(x)\nyes\n@end", "x\nyes\n"],
            'one-line if' => ['@if(x)yes@end', 'yes'],
        ];
    }

    #[DataProvider('whitespace')]
    public function testWhitespaceAsInV130(string $template, string $expected): void
    {
        $this->assertSame($expected, (new Tsuku())->process($template, self::data()));
    }

    /**
     * Braces, Magento directives and function output that must pass through unchanged.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function passthrough(): array
    {
        return [
            'magento widget' => [
                '{{widget type="Magento\\Cms\\Block\\Widget\\Block" block_id="footer"}}',
                '{{widget type="Magento\\Cms\\Block\\Widget\\Block" block_id="footer"}}',
            ],
            'magento media/store/var' => [
                '{{media url="wysiwyg/a.png"}} {{store url="checkout"}} {{var order.increment_id}}',
                '{{media url="wysiwyg/a.png"}} {{store url="checkout"}} {{var order.increment_id}}',
            ],
            'double braces around a variable are not an escape' => ['{{name}}', '{N}'],
            'json braces' => ['{"name": "{name}", "n": {num}}', '{"name": "N", "n": 5}'],
            'brace text that is not a variable' => ['{not a var} {name', '{not a var} {name'],
            'unknown function in output is removed (silent)' => ['@media(max-width: 600px) { a {} }', ' { a {} }'],
            'numeric argument stays verbatim' => ['@concat(1.50, "x")', '1.50x'],
            'missing variable argument is empty' => ['@concat(missing, "x")', 'x'],
            'comma inside quoted argument' => ['@concat("a,b", "c")', 'a,bc'],
            'nested functions' => ['@upper(@trim(pad))', 'ABC'],
            'default function' => ['@default(missing, "fallback")', 'fallback'],
            'join' => ['@join(items, ", ")', 'a, b'],
            'number with separators' => ['@number(1234.5, 2, ",", ".")', '1.234,50'],
            'replace' => ['@replace(name, "N", "M")', 'M'],
            'apostrophe inside double quotes' => ['@concat("it\'s", "x")', "it'sx"],
            'double quotes inside single quotes' => ['@concat(\'say "hi"\', "!")', 'say "hi"!'],
            'function error is silent' => ['@upper()', ''],
            'substr' => ['@substr("abcdef", 1, 3)', 'bcd'],
            'length of array' => ['@length(items)', '2'],
            'csv escaping' => ['@csv(withcomma)', '"a,b"'],
            'numeric path segments' => ['{items.0}-{items.1}-{items.5}', 'a-b-'],
            'deep path' => ['{num}{nested.a.b}', '5!'],
            'dollar before function' => ['$@number(num, 2)', '$5.00'],
            'inline condition' => ['@?{num > 1 "big" : "small"}', 'big'],
        ];
    }

    #[DataProvider('passthrough')]
    public function testOutputAsInV130(string $template, string $expected): void
    {
        $this->assertSame($expected, (new Tsuku())->process($template, self::data()));
    }

    public function testUnknownFunctionInOutputWarnsInWarningMode(): void
    {
        $tsuku = new Tsuku(StrictnessMode::WARNING);

        $this->assertSame(' { a {} }', $tsuku->process('@media(max-width: 600px) { a {} }', []));
        $this->assertSame(["Function 'media' not found"], $tsuku->getWarnings());
    }

    public function testUnknownFunctionInOutputThrowsInStrictMode(): void
    {
        $this->expectException(TsukuException::class);
        $this->expectExceptionMessage("Function 'media' not found");

        (new Tsuku(StrictnessMode::STRICT))->process('@media(max-width: 600px) { a {} }', []);
    }

    /**
     * Templates 1.3.0 rejects and 1.4.0 must keep rejecting.
     *
     * @return array<string, array{0: string}>
     */
    public static function rejected(): array
    {
        return [
            'bare at in text' => ['user@example.com'],
            'lonely at' => ['@'],
            'at followed by space' => ['@ x'],
            'else outside if' => ['@else'],
            'missing end' => ['@if(x)no end'],
            'end without block' => ['@end'],
            'invalid for' => ['@for(bad syntax)x@end'],
            'text before first case' => ['@match(c)text@case("red")R@end'],
            'case outside match' => ['@case("x")'],
            'default outside match' => ['@default'],
        ];
    }

    #[DataProvider('rejected')]
    public function testRejectedAsInV130(string $template): void
    {
        $this->expectException(ParseException::class);

        (new Tsuku())->process($template, self::data());
    }
}
