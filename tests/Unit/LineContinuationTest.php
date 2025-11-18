<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class LineContinuationTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function test_line_continuation_with_csv_functions(): void
    {
        $template = '@csv("Product A"),\
@csv("SKU123"),\
@csv("99.99")';

        $result = $this->tsuku->process($template, []);
        $this->assertSame('Product A,SKU123,99.99', $result);
    }

    public function test_line_continuation_with_variables(): void
    {
        $template = '{name},\
{sku},\
{price}';

        $result = $this->tsuku->process($template, [
            'name' => 'Widget',
            'sku' => 'WID-001',
            'price' => '29.99'
        ]);

        $this->assertSame('Widget,WID-001,29.99', $result);
    }

    public function test_line_continuation_with_plain_text(): void
    {
        $template = 'This is a long \
line of text';

        $result = $this->tsuku->process($template, []);
        $this->assertSame('This is a long line of text', $result);
    }

    public function test_line_continuation_multiple_lines(): void
    {
        $template = 'Line 1\
Line 2\
Line 3';

        $result = $this->tsuku->process($template, []);
        $this->assertSame('Line 1Line 2Line 3', $result);
    }

    public function test_line_continuation_with_functions_and_text(): void
    {
        $template = '@upper("hello"),\
@lower("WORLD"),\
{name}';

        $result = $this->tsuku->process($template, ['name' => 'Test']);
        $this->assertSame('HELLO,world,Test', $result);
    }

    public function test_line_continuation_in_loop(): void
    {
        $template = '@for(products as product)
@csv(product.name),\
@csv(product.sku),\
@csv(product.price)
@end';

        $result = $this->tsuku->process($template, [
            'products' => [
                ['name' => 'Product A', 'sku' => 'SKU-001', 'price' => '10.00'],
                ['name' => 'Product B', 'sku' => 'SKU-002', 'price' => '20.00'],
            ]
        ]);

        $this->assertSame('Product A,SKU-001,10.00
Product B,SKU-002,20.00
', $result);
    }

    public function test_line_continuation_csv_export(): void
    {
        $template = 'Name,SKU,Price,Stock
@for(products as product)
@csv(product.name),\
@csv(product.sku),\
@csv(product.price),\
{product.stock}
@end';

        $result = $this->tsuku->process($template, [
            'products' => [
                [
                    'name' => 'Widget "Pro"',
                    'sku' => 'WID-001',
                    'price' => '29.99',
                    'stock' => 100
                ],
                [
                    'name' => 'Gadget, Premium',
                    'sku' => 'GAD-002',
                    'price' => '59.99',
                    'stock' => 50
                ],
            ]
        ]);

        $expected = 'Name,SKU,Price,Stock
"Widget ""Pro""",WID-001,29.99,100
"Gadget, Premium",GAD-002,59.99,50
';

        $this->assertSame($expected, $result);
    }

    public function test_backslash_without_newline_preserved(): void
    {
        $template = 'Path: C:\Windows\System32';

        $result = $this->tsuku->process($template, []);
        $this->assertSame('Path: C:\Windows\System32', $result);
    }

    public function test_double_backslash_before_newline(): void
    {
        $template = 'First\\
Second';

        // Single backslash + newline should be removed
        $result = $this->tsuku->process($template, []);
        $this->assertSame('FirstSecond', $result);
    }

    public function test_line_continuation_windows_newline(): void
    {
        $template = "First\\\r\nSecond";

        $result = $this->tsuku->process($template, []);
        $this->assertSame('FirstSecond', $result);
    }
}
