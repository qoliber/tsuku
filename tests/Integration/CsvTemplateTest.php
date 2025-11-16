<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class CsvTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateSimpleCsv(): void
    {
        $template = "name,price\n@for(products as product)\n{product.name},{product.price}\n@end";

        $data = [
            'products' => [
                ['name' => 'Widget A', 'price' => '29.99'],
                ['name' => 'Widget B', 'price' => '39.99'],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = "name,price\nWidget A,29.99\nWidget B,39.99\n";

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('name,price', $result);
        $this->assertStringContainsString('Widget A,29.99', $result);
        $this->assertStringContainsString('Widget B,39.99', $result);
    }

    public function testGenerateCsvWithConditionals(): void
    {
        $template = "name,price,status\n@for(products as product)\n{product.name},{product.price},@if(product.stock > 0)\nIn Stock\n@end\n@unless(product.stock > 0)\nOut of Stock\n@end\n@end";

        $data = [
            'products' => [
                ['name' => 'Widget A', 'price' => '29.99', 'stock' => 10],
                ['name' => 'Widget B', 'price' => '39.99', 'stock' => 0],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = "name,price,status\nWidget A,29.99,In Stock\nWidget B,39.99,Out of Stock\n";

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('Widget A,29.99,In Stock', $result);
        $this->assertStringContainsString('Widget B,39.99,Out of Stock', $result);
    }
}
