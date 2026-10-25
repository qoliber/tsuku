<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

/**
 * The 1.4.0 whitespace rules, one data set per rule and edge case. Every template
 * with a top-level @for over "rows" is also rendered with processToStream() and
 * must be byte-identical.
 */
class WhitespaceControlTest extends TestCase
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
            'items' => ['a', 'b'],
            'c' => 'red',
            'rows' => self::rows(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(): array
    {
        return [
            ['sku' => 'A', 'qty' => 5, 'c' => 'red', 'tags' => ['t1', 't2']],
            ['sku' => 'B', 'qty' => 0, 'c' => 'blue', 'tags' => []],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rules(): array
    {
        return [
            // Rule 1: layout lines
            'layout line: indentation and line break dropped' => ["  @if(x)\n  yes\n  @end\nafter", "  yes\nafter"],
            'layout line: tabs' => ["\t@if(x)\n\tyes\n\t@end\n", "\tyes\n"],
            'layout line: trailing spaces after directive' => ["@if(x)   \nyes\n@end   \nafter", "yes\nafter"],
            'layout line: several directives on one line' => ["@for(items as i)@if(x)\n{i}\n@end @end\nz", "a\nb\nz"],
            'layout line: nested indented loops' => [
                "a\n  @for(items as i)\n    @if(x)\n    {i}\n    @end\n  @end\nz",
                "a\n    a\n    b\nz",
            ],
            'layout line: last line without newline' => ["@if(x)\nyes\n  @end", "yes\n"],
            'layout line: template starts with indentation' => ["   @if(x)\nyes\n@end", "yes\n"],
            'layout line: blank lines inside the body are content' => ["@if(x)\n\nyes\n\n@end\n", "\nyes\n\n"],
            'layout line: multi-line condition' => ["@if(x and\n    not f)\nyes\n@end\n", "yes\n"],
            'layout line: standalone match' => [
                "@match(c)\n  @case(\"red\")\n    one\n  @case(\"blue\")\n    two\n@end\n",
                "    one\n",
            ],
            'layout line: empty loop renders nothing' => ["x\n@for(missing as m)\n{m}\n@end\ny", "x\ny"],

            // Rule 2: indentation before branch directives and @end at line start
            'branch with content after it' => [
                "@match(c)\n  @case(\"red\")  R\n  @case(\"blue\") B\n@end\n",
                "  R\n",
            ],
            'else with content after it' => ["@if(f)\nyes\n  @else no\n@end\n", " no\n"],
            'end with content after it' => ["@for(items as i)\n{i}\n  @end tail", "a\nb\n tail"],

            // Rule 3: directives (not @end) at the end of a content line take its line break
            'for opened at end of a content line' => [
                "Items: @for(items as item)\n- {item}\n@end\nnext",
                "Items: - a\n- b\nnext",
            ],
            'else at end of a content line' => ["@if(f)yes@else\nno\n@end\n", "no\n"],
            'opener at end of content line with trailing spaces' => [
                "Items: @for(items as i)  \n{i};@end\n",
                "Items: a;b;\n",
            ],

            // Rule 4: @end after content keeps the line break
            'one-line if at line start' => ["@if(x)yes@end\nnext", "yes\nnext"],
            'end after content on the last body line' => ["@if(x)\nyes@end\nnext", "yes\nnext"],
            'one-line for' => ['@for(items as i){i},@end' . "\n", "a,b,\n"],
            'one-line blocks inside a loop' => ["@for(items as i)@if(x){i}@end\n@end", "a\nb\n"],

            // Rule 5: inline blocks
            'inline if, one line (brief)' => [
                "@for(rows as p)\n{p.sku},@if(p.qty > 0)in@else out@end\n@end",
                "A,in\nB, out\n",
            ],
            'inline match, indented cases (brief)' => [
                "@for(rows as p)\n{p.sku},@match(p.c)\n  @case(\"red\")R\n  @case(\"blue\")B\n@end\n@end",
                "A,R\nB,B\n",
            ],
            'inline match, case bodies on their own lines' => [
                "@for(rows as p)\n{p.sku},@match(p.c)\n  @case(\"red\")\n    R\n  @case(\"blue\")\n    B\n@end\n@end",
                "A,R\nB,B\n",
            ],
            'inline if with indented branches' => [
                "@for(rows as p)\n{p.sku},@if(p.qty > 0)\n  in\n@else\n  out\n@end\n@end",
                "A,in\nB,out\n",
            ],
            'inline if, single-line body keeps its spaces' => ["{name}: @if(x) yes \n@end\n", "N:  yes \n"],
            'inline block closed before content' => [
                "@for(rows as p)\n<stock>@match(p.c)\n  @case(\"red\")\n    in_stock\n"
                . "  @default\n    out_of_stock\n@end</stock>\n@end",
                "<stock>in_stock</stock>\n<stock>out_of_stock</stock>\n",
            ],
            'inline block closed before content keeps inner line breaks' => [
                "<p>@if(x)\n  one\n  two\n@end</p>",
                "<p>one\n  two</p>",
            ],
            'inline block with a nested standalone block' => [
                "@for(rows as p)\n{p.sku},@if(p.qty > 0)\n  @match(p.c)\n    @case(\"red\")R\n  @end\n"
                . "@else\n  out\n@end\n@end",
                "A,R\nB,out\n",
            ],
            'two inline conditionals completing one CSV row (1.3.0 idiom)' => [
                "@for(rows as p)\n{p.sku},@if(p.qty > 0)\nIn Stock\n@end\n@unless(p.qty > 0)\nOut of Stock\n@end\n@end",
                "A,In Stock\nB,Out of Stock\n",
            ],
            'inline for body is never trimmed' => [
                "<tags>@for(items as t)\n  <tag>{t}</tag>\n@end</tags>",
                "<tags>  <tag>a</tag>\n  <tag>b</tag>\n</tags>",
            ],
            'inline elseif chain' => [
                "@for(rows as p)\n{p.sku}=@if(p.qty > 3)\n  many\n@elseif(p.qty > 0)\n  few\n@else\n  none\n@end\n@end",
                "A=many\nB=none\n",
            ],

            // CRLF
            'crlf layout lines' => ["@if(x)\r\nyes\r\n@end\r\nz", "yes\r\nz"],
            'crlf inline match' => [
                "@for(rows as p)\r\n{p.sku},@match(p.c)\r\n  @case(\"red\")R\r\n  @case(\"blue\")B\r\n@end\r\n@end",
                "A,R\r\nB,B\r\n",
            ],
            'crlf inline end keeps the line break' => [
                "@for(rows as p)\r\n{p.sku}@if(p.qty > 0)+@end\r\n@end",
                "A+\r\nB\r\n",
            ],

            // Line continuation runs first and is unchanged
            'continuation joins lines before the rules apply' => [
                "@for(rows as p)\n{p.sku},\\\n@if(p.qty > 0)in@else out@end\n@end",
                "A,in\nB, out\n",
            ],
            'continuation inside a condition' => ["@if(x and \\\n f)Y@else N@end", ' N'],
        ];
    }

    #[DataProvider('rules')]
    public function testRule(string $template, string $expected): void
    {
        $tsuku = new Tsuku();

        $this->assertSame($expected, $tsuku->process($template, self::data()));

        if (preg_match('/^@for\(rows as /m', $template) === 1) {
            $streamed = '';
            $writer = function (string $chunk) use (&$streamed): void {
                $streamed .= $chunk;
            };
            $tsuku->processToStream($template, self::data(), self::rows(), 'rows', $writer);
            $this->assertSame($expected, $streamed, 'processToStream must match process()');
        }
    }

    public function testCsvFeedWithoutContinuations(): void
    {
        $template = <<<'TPL'
sku,status,tags
@for(rows as p)
{p.sku},@match(p.c)
    @case("red")
        hot
    @default
        cold
@end,@join(p.tags, "|")
@end
TPL;

        $this->assertSame("sku,status,tags\nA,hot,t1|t2\nB,cold,\n", (new Tsuku())->process($template, self::data()));
    }

    public function test130IdiomOfJoiningLinesWithEndNeedsContinuations(): void
    {
        // 1.3.0 swallowed the line break after every @end, which some templates used
        // to glue lines together; since 1.4.0 that takes a "\" continuation.
        $old = "<e@if(x)\n a=\"1\"@end\n/>";
        $new = "<e@if(x) a=\"1\"@end\\\n/>";

        $this->assertSame("<ea=\"1\"\n/>", (new Tsuku())->process($old, self::data()));
        $this->assertSame('<e a="1"/>', (new Tsuku())->process($new, self::data()));
    }
}
