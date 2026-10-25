<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Function;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Exception\FunctionNotFoundException;
use Qoliber\Tsuku\Exception\TsukuException;
use Qoliber\Tsuku\Function\FunctionRegistry;
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
}
