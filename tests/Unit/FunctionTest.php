<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class FunctionTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testUpperFunction(): void
    {
        $template = 'Hello @upper(name)!';
        $data = ['name' => 'world'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('Hello WORLD!', $result);
    }

    public function testLowerFunction(): void
    {
        $template = '@lower(greeting)';
        $data = ['greeting' => 'HELLO WORLD'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('hello world', $result);
    }

    public function testCapitalizeFunction(): void
    {
        $template = '@capitalize(name)';
        $data = ['name' => 'john'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('John', $result);
    }

    public function testTrimFunction(): void
    {
        $template = '@trim(text)';
        $data = ['text' => '  hello  '];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('hello', $result);
    }

    public function testConcatFunction(): void
    {
        $template = '@concat(first, " ", last)';
        $data = ['first' => 'John', 'last' => 'Doe'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('John Doe', $result);
    }

    public function testSubstrFunction(): void
    {
        $template = '@substr(text, 0, 5)';
        $data = ['text' => 'Hello World'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('Hello', $result);
    }

    public function testSubstrFunctionWithoutLength(): void
    {
        $template = '@substr(text, 6)';
        $data = ['text' => 'Hello World'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('World', $result);
    }

    public function testReplaceFunction(): void
    {
        $template = '@replace(text, "World", "PHP")';
        $data = ['text' => 'Hello World'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('Hello PHP', $result);
    }

    public function testAbsFunction(): void
    {
        $template = '@abs(number)';
        $data = ['number' => -42];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('42', $result);
    }

    public function testRoundFunction(): void
    {
        $template = '@round(pi, 2)';
        $data = ['pi' => 3.14159];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('3.14', $result);
    }

    public function testCeilFunction(): void
    {
        $template = '@ceil(number)';
        $data = ['number' => 3.14];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('4', $result);
    }

    public function testFloorFunction(): void
    {
        $template = '@floor(number)';
        $data = ['number' => 3.99];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('3', $result);
    }

    public function testNumberFormatFunction(): void
    {
        $template = '@number_format(price, 2)';
        $data = ['price' => 1234.5];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('1,234.50', $result);
    }

    public function testNumberFormatWithCustomSeparators(): void
    {
        $template = '@number_format(price, 2, ",", " ")';
        $data = ['price' => 1234.5];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('1 234,50', $result);
    }

    public function testDateFunction(): void
    {
        $template = '@date("Y-m-d", timestamp)';
        $data = ['timestamp' => 1609459200]; // 2021-01-01 00:00:00 UTC

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('2021-01-01', $result);
    }

    public function testDateFunctionWithoutTimestamp(): void
    {
        $template = '@date("Y")';
        $data = [];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame(date('Y'), $result);
    }

    public function testStrtotimeFunction(): void
    {
        $template = '@strtotime(dateString)';
        $data = ['dateString' => '2021-01-01'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('1609459200', $result);
    }

    public function testDefaultFunction(): void
    {
        $template = '@default(value, "N/A")';
        $data = ['value' => ''];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('N/A', $result);
    }

    public function testDefaultFunctionWithValue(): void
    {
        $template = '@default(value, "N/A")';
        $data = ['value' => 'Present'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('Present', $result);
    }

    public function testLengthFunctionWithString(): void
    {
        $template = '@length(text)';
        $data = ['text' => 'Hello'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('5', $result);
    }

    public function testLengthFunctionWithArray(): void
    {
        $template = '@length(items)';
        $data = ['items' => ['a', 'b', 'c']];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('3', $result);
    }

    public function testJoinFunction(): void
    {
        $template = '@join(items, ", ")';
        $data = ['items' => ['apple', 'banana', 'orange']];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('apple, banana, orange', $result);
    }

    public function testFirstFunction(): void
    {
        $template = '@first(items)';
        $data = ['items' => ['apple', 'banana', 'orange']];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('apple', $result);
    }

    public function testLastFunction(): void
    {
        $template = '@last(items)';
        $data = ['items' => ['apple', 'banana', 'orange']];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('orange', $result);
    }

    public function testFunctionInLoop(): void
    {
        $template = '@for(items as item)
@upper(item)
@end';
        $data = ['items' => ['apple', 'banana', 'orange']];

        $result = $this->tsuku->process($template, $data);

        $expected = 'APPLE
BANANA
ORANGE
';
        $this->assertSame($expected, $result);
    }

    public function testFunctionWithLiteralString(): void
    {
        $template = '@upper("hello world")';
        $data = [];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('HELLO WORLD', $result);
    }

    public function testNestedFunctionCalls(): void
    {
        // Nested function calls are now supported with the new () syntax!
        $template = '@upper(@trim(name))';
        $data = ['name' => '  hello world  '];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('HELLO WORLD', $result);

        // Test deeper nesting
        $template2 = '@upper(@capitalize(@trim(text)))';
        $data2 = ['text' => '  hello  '];

        $result2 = $this->tsuku->process($template2, $data2);

        $this->assertSame('HELLO', $result2);
    }

    public function testFunctionWithDotNotation(): void
    {
        $template = '@upper(user.name)';
        $data = ['user' => ['name' => 'john']];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('JOHN', $result);
    }

    public function testMultipleFunctionsInTemplate(): void
    {
        $template = '@upper(first) and @lower(second)';
        $data = ['first' => 'hello', 'second' => 'WORLD'];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('HELLO and world', $result);
    }

    public function testFunctionInConditional(): void
    {
        // Functions in conditional expressions are now supported!
        $template = '@if(@length(items) > 2)
Many items
@end';
        $data = ['items' => ['apple', 'banana', 'orange']];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('Many items', $result);

        // Test false case
        $template2 = '@if(@length(items) == 0)
No items
@end';
        $data2 = ['items' => []];

        $result2 = $this->tsuku->process($template2, $data2);

        $this->assertStringContainsString('No items', $result2);
    }

    public function testFunctionInTernary(): void
    {
        // Functions in ternary branches are now supported!
        $template = '@?{items @join(items, ", ") : "No items"}';
        $data = ['items' => ['apple', 'banana']];

        $result = $this->tsuku->process($template, $data);

        $this->assertSame('apple, banana', $result);

        // Test false branch with function
        $template2 = '@?{items "Found" : @upper("empty")}';
        $data2 = ['items' => []];

        $result2 = $this->tsuku->process($template2, $data2);

        $this->assertSame('EMPTY', $result2);
    }

    public function testFullTextCompareWithFunctions(): void
    {
        $template = 'Product: @upper(product.name)
Price: $@number_format(product.price, 2)
Status: @default(product.status, "Available")';

        $data = [
            'product' => [
                'name' => 'laptop',
                'price' => 1299.99,
                'status' => '',
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = 'Product: LAPTOP
Price: $1,299.99
Status: Available';

        $this->assertSame($expected, $result);
    }
}
