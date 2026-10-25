<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Tsuku;

/**
 * processToStream() renders byte-identical output to process() for every 1.4.0 feature.
 */
class StreamingParityTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private static function rows(): array
    {
        return [
            ['sku' => 'A', 'qty' => 5, 'c' => 'red', 'groups' => '7231,12', 'name' => 'Fish', 'tags' => ['new']],
            ['sku' => 'B', 'qty' => 0, 'c' => 'blue', 'groups' => '12', 'name' => 'Tea', 'tags' => []],
            ['sku' => 'C', 'qty' => 2, 'c' => 'green', 'groups' => '', 'name' => 'Cake', 'tags' => ['new', 'sale']],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function templates(): array
    {
        return [
            'csv with inline if' => ["sku,stock\n@for(rows as p)\n{p.sku},@if(p.qty > 0)in@else out@end\n@end"],
            'csv with indented inline match' => [
                "sku,colour\n@for(rows as p)\n{p.sku},@match(p.c)\n  @case(\"red\")R\n  @case(\"blue\")B\n"
                . "  @default\n    other\n@end\n@end",
            ],
            'xml with layout lines' => [
                "<?xml version=\"1.0\"?>\n<feed currency=\"{currency}\">\n  @for(rows as p)\n  <item>\n"
                . "    @if(p.qty > 0 and \"7231\" in @split(p.groups, \",\"))\n    <promo/>\n"
                . "    @elseif(p.qty > 0)\n    <stock/>\n    @end\n    <name>@cdata\n      {p.name}\n"
                . "    @end</name>\n  </item>\n  @end\n</feed>\n",
            ],
            'crlf template' => ["h\r\n@for(rows as p)\r\n  @if(p.qty)\r\n  {p.sku}\r\n  @end\r\n@end\r\nf"],
            'no trailing newline and key variable' => [
                "@for(rows as p, i){i}={p.sku}@unless(@length(p.tags)) (untagged)@end\n@end",
            ],
            'escapes and inline conditions' => [
                "@for(rows as p)\n@@{p.sku} @?{p.qty > 0 or p.c == \"blue\" \"ok\" : \"-\"}\n@end",
            ],
            'continuations' => [
                "@for(rows as p)\n{p.sku},\\\n@if(@contains(p.tags, \"new\"))new@end,\\\n{p.qty}\n@end",
            ],
        ];
    }

    #[DataProvider('templates')]
    public function testStreamedOutputEqualsProcess(string $template): void
    {
        $tsuku = new Tsuku();
        $tsuku->registerDirective('cdata', fn(string $body): string => '<![CDATA[' . $body . ']]>');

        $expected = $tsuku->process($template, ['currency' => 'EUR', 'rows' => self::rows()]);

        $chunks = [];
        $writer = function (string $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        };
        $tsuku->processToStream($template, ['currency' => 'EUR'], self::rows(), 'rows', $writer);

        $this->assertSame($expected, implode('', $chunks));
        $this->assertGreaterThan(1, count($chunks));
    }

    public function testStreamingWithGeneratorAndStrictMode(): void
    {
        $template = "@for(rows as p)\n{p.sku}@if(p.qty > 0 && p.sku != \"C\")!@end\n@end";
        $generator = (function (): \Generator {
            yield from self::rows();
        })();

        $streamed = '';
        $writer = function (string $chunk) use (&$streamed): void {
            $streamed .= $chunk;
        };
        (new Tsuku())->processToStream($template, [], $generator, 'rows', $writer, StrictnessMode::STRICT);

        $this->assertSame("A!\nB\nC\n", $streamed);
    }
}
