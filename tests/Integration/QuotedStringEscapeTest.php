<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Tsuku;

/**
 * Inside "...", \" is a literal double quote; inside '...', \' is a literal single quote.
 * Every other backslash is kept as typed.
 */
class QuotedStringEscapeTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku(StrictnessMode::STRICT);
        $this->tsuku->registerDirective(
            'wrap',
            fn(string $body, string $before, string $after): string => $before . $body . $after
        );
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function escapedQuoteProvider(): array
    {
        return [
            'function argument' => ['@concat("say \"hi\"")', [], 'say "hi"'],
            'single-quoted argument' => ["@concat('it\\'s')", [], "it's"],
            'several arguments' => ['@concat("a \"b\"", ", ", "\"c\"")', [], 'a "b", "c"'],
            'nested call' => ['@upper(@concat("x \"y\""))', [], 'X "Y"'],
            'ternary value' => ['@?{flag "a \"b\"" : "c"}', ['flag' => true], 'a "b"'],
            'ternary false value' => ["@?{flag 'a' : 'it\\'s'}", ['flag' => false], "it's"],
            'condition literal' => ['@if(name == "say \"hi\"")match@end', ['name' => 'say "hi"'], 'match'],
            'in literal' => ["@if(\"\\\"\" in name)quoted@end", ['name' => 'say "hi"'], 'quoted'],
            'case value' => ['@match(name)@case("say \"hi\"")match@end', ['name' => 'say "hi"'], 'match'],
            'match subject' => ['@match("a \"b\"")@case(v)match@end', ['v' => 'a "b"'], 'match'],
            'directive argument' => ['@wrap("\"", "\"")body@end', [], '"body"'],
            'other quote kept' => ["@concat(\"it\\'s\")", [], "it\\'s"],
            'backslash kept' => ['@concat("C:\path\to")', [], 'C:\path\to'],
            'double backslash kept' => ['@concat("a\\\\b")', [], 'a\\\\b'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('escapedQuoteProvider')]
    public function testEscapedQuotes(string $template, array $data, string $expected): void
    {
        $this->assertSame($expected, $this->tsuku->process($template, $data));
    }
}
