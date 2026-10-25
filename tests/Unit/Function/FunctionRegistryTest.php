<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Function;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Exception\TsukuException;
use Qoliber\Tsuku\Function\FunctionRegistry;
use Qoliber\Tsuku\StrictnessMode;
use Qoliber\Tsuku\Tsuku;

class FunctionRegistryTest extends TestCase
{
    private FunctionRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new FunctionRegistry();
    }

    public function testExecuteUnknownFunctionThrowsTypedException(): void
    {
        try {
            $this->registry->execute('nope', []);
            $this->fail('expected FunctionNotFoundException');
        } catch (FunctionNotFoundException $e) {
            $this->assertInstanceOf(TsukuException::class, $e);
            $this->assertSame("Function 'nope' not found", $e->getMessage());
            $this->assertSame('nope', $e->getFunctionName());
        }
    }

    public function testRegisterOverridesBuiltIn(): void
    {
        $this->registry->register('upper', fn(string $s): string => 'custom ' . $s);

        $this->assertSame('custom a', $this->registry->execute('upper', ['a']));
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed, 2: bool}>
     */
    public static function containsCases(): array
    {
        return [
            'list' => [['7231', '12'], 7231, true],
            'string' => ['7231,12', '31', true],
            'missing' => [['a'], 'b', false],
            'number haystack' => [12, '1', false],
        ];
    }

    #[DataProvider('containsCases')]
    public function testContains(mixed $haystack, mixed $needle, bool $expected): void
    {
        $this->assertSame($expected, $this->registry->execute('contains', [$haystack, $needle]));
    }

    /**
     * @return array<string, array{0: array<int, mixed>, 1: array<int|string, mixed>}>
     */
    public static function splitCases(): array
    {
        return [
            'default delimiter' => [['7231,12'], ['7231', '12']],
            'custom delimiter' => [['a | b', ' | '], ['a', 'b']],
            'pieces are not trimmed' => [['a, b'], ['a', ' b']],
            'empty pieces kept' => [['a,,b,'], ['a', '', 'b', '']],
            'no delimiter in string' => [['abc', ';'], ['abc']],
            'empty string' => [[''], []],
            'null' => [[null], []],
            'number' => [[1.5, '.'], ['1', '5']],
            'list is returned unchanged' => [[['x' => 1]], ['x' => 1]],
            'empty delimiter splits characters' => [['añb', ''], ['a', 'ñ', 'b']],
            'empty delimiter with invalid utf-8 splits bytes' => [["a\xFF", ''], ['a', "\xFF"]],
        ];
    }

    /**
     * @param array<int, mixed> $arguments
     * @param array<int|string, mixed> $expected
     */
    #[DataProvider('splitCases')]
    public function testSplit(array $arguments, array $expected): void
    {
        $this->assertSame($expected, $this->registry->execute('split', $arguments));
    }

    public function testContainsAndSplitInTemplates(): void
    {
        $tsuku = new Tsuku();
        $data = ['groups' => '7231,12', 'tags' => ['a', 'b']];

        $template = '@contains(groups, "723")|@contains(@split(groups, ","), "723")';
        $this->assertSame('1|', $tsuku->process($template, $data));
        $this->assertSame('2:12-7231', $tsuku->process('@length(@split(groups)):@join(@split("12,7231"), "-")', $data));
        $this->assertSame('yes', $tsuku->process('@if(@contains(tags, "b"))yes@end', $data));
    }

    public function testFirstAndLastUseValuesOfAssociativeArrays(): void
    {
        $map = ['x' => 'first', 'y' => 'middle', 'z' => 'last'];

        $this->assertSame('first', $this->registry->execute('first', [$map]));
        $this->assertSame('last', $this->registry->execute('last', [$map]));
        $this->assertSame('b', $this->registry->execute('last', [[5 => 'a', 2 => 'b']]));
    }

    public function testEscapeFunctionsAreUnchanged(): void
    {
        $value = 'a "b" & <c>, d';

        $this->assertSame('a &quot;b&quot; &amp; &lt;c&gt;, d', $this->registry->execute('html', [$value]));
        $html = $this->registry->execute('html', [$value]);
        $this->assertSame($html, $this->registry->execute('escape', [$value]));
        $this->assertSame($html, $this->registry->execute('escape', [$value, 'other']));
        $this->assertSame('a &quot;b&quot; &amp; &lt;c&gt;, d', $this->registry->execute('escape', [$value, 'xml']));
        $json = '"a \\u0022b\\u0022 \\u0026 \\u003Cc\\u003E, d"';
        $this->assertSame($json, $this->registry->execute('escape', [$value, 'json']));
        $this->assertSame('a%20%22b%22%20%26%20%3Cc%3E%2C%20d', $this->registry->execute('escape', [$value, 'url']));
        $this->assertSame('"a ""b"" & <c>, d"', $this->registry->execute('escape', [$value, 'csv']));
        $this->assertSame('plain', $this->registry->execute('csv', ['plain']));
        $this->assertSame("\"two\nlines\"", $this->registry->execute('csv', ["two\nlines"]));
        $this->assertSame('"say ""hi"""', $this->registry->execute('csv', ['say "hi"']));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function uncallableNameProvider(): array
    {
        return [
            'empty' => ['', 'Invalid function name'],
            'leading digit' => ['1st', 'Invalid function name'],
            'hyphen' => ['my-func', 'Invalid function name'],
            'space' => ['my func', 'Invalid function name'],
            'at sign' => ['@upper', 'Invalid function name'],
            'if' => ['if', 'built-in directive'],
            'for' => ['for', 'built-in directive'],
            'unless' => ['unless', 'built-in directive'],
            'match' => ['match', 'built-in directive'],
            'case' => ['case', 'built-in directive'],
            'elseif' => ['elseif', 'built-in directive'],
            'else' => ['else', 'built-in directive'],
            'end' => ['end', 'built-in directive'],
            'starts with end' => ['endsWith', 'read as the @end directive'],
            'starts with else' => ['elsewhere', 'read as the @else directive'],
            'starts with default' => ['defaultValue', 'read as the @default directive'],
        ];
    }

    #[DataProvider('uncallableNameProvider')]
    public function testRegisterRefusesNamesThatCanNeverBeCalled(string $name, string $reason): void
    {
        try {
            $this->registry->register($name, static fn(): string => 'x');
            $this->fail("'{$name}' must be refused");
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString($reason, $e->getMessage());
            $this->assertStringContainsString("'{$name}'", $e->getMessage());
        }

        $this->assertFalse($this->registry->has($name));
    }

    #[DataProvider('uncallableNameProvider')]
    public function testTsukuRegisterFunctionRefusesNamesThatCanNeverBeCalled(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Tsuku())->registerFunction($name, static fn(): string => 'x');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function callableNameProvider(): array
    {
        return [
            'snake case' => ['price_format'],
            'leading underscore' => ['_private'],
            'digits' => ['iso8601'],
            'starts like a directive' => ['ifEmpty'],
            'formatted' => ['format'],
            'the default function' => ['default'],
            'unless-like' => ['unlessEmpty'],
            'case-like' => ['casefold'],
        ];
    }

    #[DataProvider('callableNameProvider')]
    public function testRegisteredNamesCanBeCalledFromATemplate(string $name): void
    {
        $tsuku = new Tsuku();
        $tsuku->registerFunction($name, static fn(string $value): string => "<{$value}>");

        $this->assertSame('<a>', $tsuku->process("@{$name}(v)", ['v' => 'a']));
        $this->assertSame('yes', $tsuku->process("@if(@{$name}(v) == \"<a>\")yes@end", ['v' => 'a']));
    }

    public function testStringFunctionsAcceptNumbersFromTheData(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);
        $data = ['sku' => 123456, 'code' => 1001, 'price' => 9.5, 'flag' => true, 'nothing' => null];

        $this->assertSame('123', $tsuku->process('@substr(sku, 0, 3)', $data));
        $this->assertSame('456', $tsuku->process('@substr(sku, 3)', $data));
        $this->assertSame('9.', $tsuku->process('@substr(price, 0, 2)', $data));
        $this->assertSame('1', $tsuku->process('@substr(flag, 0)', $data));
        $this->assertSame('1--1', $tsuku->process('@replace(code, "0", "-")', $data));
        $this->assertSame('1001', $tsuku->process('@replace(sku, code, "x")', ['sku' => 1001, 'code' => 7]));
        $this->assertSame('x', $tsuku->process('@replace(sku, code, "x")', ['sku' => 1001, 'code' => 1001]));
        $this->assertSame('2-2', $tsuku->process(
            '@replace(code, from, to)',
            ['code' => 212, 'from' => 1, 'to' => '-']
        ));
    }

    public function testDateFunctionsAcceptNumbersFromTheData(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);

        $this->assertSame(
            (string) strtotime('2026'),
            $tsuku->process('@strtotime(year)', ['year' => 2026])
        );
        $this->assertSame('1970', $tsuku->process('@date(format, 0)', ['format' => 'Y']));
        $this->assertSame('2026', $tsuku->process('@date(format, 1792886400)', ['format' => 2026]));
    }

    public function testFormattingFunctionsAcceptNumbersAsSeparators(): void
    {
        $tsuku = new Tsuku(StrictnessMode::STRICT);
        $data = ['amount' => 1234.5, 'zero' => 0, 'list' => ['a', 'b'], 'glue' => 0];

        $this->assertSame('1023405', $tsuku->process('@number(amount, 1, zero, zero)', $data));
        $this->assertSame('1023405', $tsuku->process('@number_format(amount, 1, zero, zero)', $data));
        $this->assertSame('a0b', $tsuku->process('@join(list, glue)', $data));
    }

    public function testMissingValuesGiveEmptyStringsInStringFunctions(): void
    {
        $tsuku = new Tsuku(StrictnessMode::SILENT);

        $this->assertSame('[]', $tsuku->process('[@substr(missing, 0, 3)]', []));
        $this->assertSame('[]', $tsuku->process('[@replace(missing, "a", "b")]', []));
    }
}
