<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class TernaryExpressionTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testSimpleTernaryWithStrings(): void
    {
        $template = 'Status: @?{stock > 0 "In Stock" : "Out of Stock"}';

        $result = $this->tsuku->process($template, ['stock' => 10]);
        $this->assertEquals('Status: In Stock', $result);

        $result = $this->tsuku->process($template, ['stock' => 0]);
        $this->assertEquals('Status: Out of Stock', $result);
    }

    public function testTernaryWithVariables(): void
    {
        $template = 'Price: $@?{active > 0 fullPrice : discountPrice}';

        $data = ['active' => 1, 'fullPrice' => '99.99', 'discountPrice' => '79.99'];
        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Price: $99.99', $result);

        $data = ['active' => 0, 'fullPrice' => '99.99', 'discountPrice' => '79.99'];
        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Price: $79.99', $result);
    }

    public function testTernaryWithNumbers(): void
    {
        $template = 'Shipping: $@?{total > 100 0 : 9.99}';

        $result = $this->tsuku->process($template, ['total' => 150]);
        $this->assertEquals('Shipping: $0', $result);

        $result = $this->tsuku->process($template, ['total' => 50]);
        $this->assertEquals('Shipping: $9.99', $result);
    }

    public function testTernaryInLoop(): void
    {
        $template = '@for(products as product)
{product.name}: @?{product.stock > 0 "Available" : "Sold Out"}
@end';

        $data = [
            'products' => [
                ['name' => 'Widget A', 'stock' => 10],
                ['name' => 'Widget B', 'stock' => 0],
                ['name' => 'Widget C', 'stock' => 5],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = 'Widget A: Available
Widget B: Sold Out
Widget C: Available
';

        $this->assertEquals($expected, $result);
    }

    public function testMultipleTernariesInTemplate(): void
    {
        $template = 'Stock: @?{stock > 0 "✓" : "✗"} | Shipping: @?{total > 50 "Free" : "Paid"}';

        $data = ['stock' => 10, 'total' => 75];
        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Stock: ✓ | Shipping: Free', $result);

        $data = ['stock' => 0, 'total' => 25];
        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Stock: ✗ | Shipping: Paid', $result);
    }

    public function testTernaryWithComparison(): void
    {
        $template = '@?{price >= 100 "Premium" : "Standard"}';

        $result = $this->tsuku->process($template, ['price' => 150]);
        $this->assertEquals('Premium', $result);

        $result = $this->tsuku->process($template, ['price' => 50]);
        $this->assertEquals('Standard', $result);
    }

    public function testTernaryWithEquality(): void
    {
        $template = '@?{status == "active" "Yes" : "No"}';

        $result = $this->tsuku->process($template, ['status' => 'active']);
        $this->assertEquals('Yes', $result);

        $result = $this->tsuku->process($template, ['status' => 'inactive']);
        $this->assertEquals('No', $result);
    }
}
