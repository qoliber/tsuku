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

    public function test_number_formats_with_defaults(): void
    {
        $template = '@number(1234.567)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,235', $result);
    }

    public function test_number_formats_with_decimals(): void
    {
        $template = '@number(1234.567, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,234.57', $result);
    }

    public function test_number_formats_with_custom_separators(): void
    {
        $template = '@number(1234.567, 2, ",", ".")';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1.234,57', $result);
    }

    public function test_number_formats_european_style(): void
    {
        $template = '@number(1234567.89, 2, ",", " ")';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1 234 567,89', $result);
    }

    public function test_number_format_alias_works(): void
    {
        $template = '@number_format(1234.56, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,234.56', $result);
    }

    public function test_number_formatting_in_template_with_variables(): void
    {
        $template = 'Price: $@number(product.price, 2)';
        $result = $this->tsuku->process($template, [
            'product' => ['price' => 1299.99],
        ]);

        $this->assertSame('Price: $1,299.99', $result);
    }

    public function test_number_formatting_in_loop(): void
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

    public function test_number_formatting_with_zero(): void
    {
        $template = '@number(0, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('0.00', $result);
    }

    public function test_number_formatting_with_negative(): void
    {
        $template = '@number(-1234.56, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('-1,234.56', $result);
    }

    public function test_number_formatting_large_number(): void
    {
        $template = '@number(1234567890.123, 3)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1,234,567,890.123', $result);
    }

    public function test_number_formatting_no_thousands_separator(): void
    {
        $template = '@number(1234.56, 2, ".", "")';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234.56', $result);
    }

    public function test_round_function(): void
    {
        $template = '@round(1234.567, 2)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234.57', $result);
    }

    public function test_ceil_function(): void
    {
        $template = '@ceil(1234.1)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1235', $result);
    }

    public function test_floor_function(): void
    {
        $template = '@floor(1234.9)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234', $result);
    }

    public function test_abs_function(): void
    {
        $template = '@abs(-1234.56)';
        $result = $this->tsuku->process($template, []);

        $this->assertSame('1234.56', $result);
    }
}
