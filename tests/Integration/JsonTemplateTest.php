<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class JsonTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateJsonProductCatalog(): void
    {
        $template = '{
  "products": [
@for(products as product)
    {
      "id": "{product.id}",
      "name": "{product.name}",
      "price": {product.price}
    }
@end
  ]
}';

        $data = [
            'products' => [
                ['id' => '1', 'name' => 'Widget', 'price' => '29.99'],
                ['id' => '2', 'name' => 'Gadget', 'price' => '39.99'],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = '{
  "products": [
    {
      "id": "1",
      "name": "Widget",
      "price": 29.99
    }
    {
      "id": "2",
      "name": "Gadget",
      "price": 39.99
    }
  ]
}';

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('"products":', $result);
        $this->assertStringContainsString('"id": "1"', $result);
        $this->assertStringContainsString('"name": "Widget"', $result);
        $this->assertStringContainsString('"price": 29.99', $result);
        $this->assertStringContainsString('"id": "2"', $result);
    }
}
