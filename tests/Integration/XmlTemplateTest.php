<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class XmlTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateXmlProductCatalog(): void
    {
        $template = '<?xml version="1.0"?>
<catalog>
@for(products as product)
  <product id="{product.id}">
    <name>{product.name}</name>
    <price>{product.price}</price>
@if(product.stock > 0)
    <availability>in-stock</availability>
@end
  </product>
@end
</catalog>';

        $data = [
            'products' => [
                ['id' => '1', 'name' => 'Premium Widget', 'price' => '49.99', 'stock' => 25],
                ['id' => '2', 'name' => 'Basic Widget', 'price' => '19.99', 'stock' => 0],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = '<?xml version="1.0"?>
<catalog>
  <product id="1">
    <name>Premium Widget</name>
    <price>49.99</price>
    <availability>in-stock</availability>
  </product>
  <product id="2">
    <name>Basic Widget</name>
    <price>19.99</price>
  </product>
</catalog>';

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('<product id="1">', $result);
        $this->assertStringContainsString('<name>Premium Widget</name>', $result);
        $this->assertStringContainsString('<price>49.99</price>', $result);
        $this->assertStringContainsString('<availability>in-stock</availability>', $result);
        $this->assertStringContainsString('<product id="2">', $result);
        $this->assertStringContainsString('<name>Basic Widget</name>', $result);
        // Product 2 should NOT have availability tag (stock is 0)
        $this->assertStringNotContainsString('<product id="2">
    <name>Basic Widget</name>
    <price>19.99</price>
    <availability>in-stock</availability>', $result);
    }
}
