<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class NumberFormattingTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testNumberFormatsWithDefaults(): void
    {
        $template = '@number(1234.567)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,235', $result);
    }

    public function testNumberFormatsWithDecimals(): void
    {
        $template = '@number(1234.567, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,234.57', $result);
    }

    public function testNumberFormatsWithCustomSeparators(): void
    {
        $template = '@number(1234.567, 2, ",", ".")';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1.234,57', $result);
    }

    public function testNumberFormatsEuropeanStyle(): void
    {
        $template = '@number(1234567.89, 2, ",", " ")';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1 234 567,89', $result);
    }

    public function testNumberFormatAliasWorks(): void
    {
        $template = '@number_format(1234.56, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,234.56', $result);
    }

    public function testNumberFormattingInTemplateWithVariables(): void
    {
        $template = 'Price: $@number(product.price, 2)';
        $result = $this->tsuku->process($template, [
            'product' => ['price' => 1299.99],
        ]);

        $this->assertSame('Price: $1,299.99', $result);
    }

    public function testNumberFormattingInLoop(): void
    {
        $template = '@for(products as product)
Product: {product.name} - $@number(product.price, 2)
@end';

        $result = $this->tsuku->process($template, [
            'products' => [
                ['name' => 'Widget', 'price' => 1234.56],
                ['name' => 'Gadget', 'price' => 9876.54],
            ],
        ]);

        $expected = 'Product: Widget - $1,234.56
Product: Gadget - $9,876.54
';

        $this->assertSame($expected, $result);
    }

    public function testNumberFormattingWithZero(): void
    {
        $template = '@number(0, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('0.00', $result);
    }

    public function testNumberFormattingWithNegative(): void
    {
        $template = '@number(-1234.56, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('-1,234.56', $result);
    }

    public function testNumberFormattingLargeNumber(): void
    {
        $template = '@number(1234567890.123, 3)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,234,567,890.123', $result);
    }

    public function testNumberFormattingNoThousandsSeparator(): void
    {
        $template = '@number(1234.56, 2, ".", "")';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234.56', $result);
    }

    public function testRoundFunction(): void
    {
        $template = '@round(1234.567, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234.57', $result);
    }

    public function testCeilFunction(): void
    {
        $template = '@ceil(1234.1)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1235', $result);
    }

    public function testFloorFunction(): void
    {
        $template = '@floor(1234.9)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234', $result);
    }

    public function testAbsFunction(): void
    {
        $template = '@abs(-1234.56)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234.56', $result);
    }
}
