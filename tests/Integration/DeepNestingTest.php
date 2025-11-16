<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class DeepNestingTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testTripleNestedLoops(): void
    {
        $template = '@for(categories as category)
Category: {category.name}
    @for(category.products as product)
      Product: {product.name}
        @for(product.variants as variant)
            Variant: {variant.sku} - ${variant.price}
        @end
    @end
@end';

        $data = [
            'categories' => [
                [
                    'name' => 'Electronics',
                    'products' => [
                        [
                            'name' => 'Laptop',
                            'variants' => [
                                ['sku' => 'LAP-001', 'price' => '999.99'],
                                ['sku' => 'LAP-002', 'price' => '1299.99'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = 'Category: Electronics
          Product: Laptop
                    Variant: LAP-001 - $999.99
                    Variant: LAP-002 - $1299.99
            ';

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('Category: Electronics', $result);
        $this->assertStringContainsString('Product: Laptop', $result);
        $this->assertStringContainsString('Variant: LAP-001 - $999.99', $result);
        $this->assertStringContainsString('Variant: LAP-002 - $1299.99', $result);
    }

    public function testNestedConditionals(): void
    {
        $template = '@for(users as user)
User: {user.name}
    @if(user.active > 0)
      Status: Active
        @if(user.premium > 0)
            Premium Member
        @end
    @end
    @unless(user.active > 0)
      Status: Inactive
    @end
@end';

        $data = [
            'users' => [
                ['name' => 'Alice', 'active' => 1, 'premium' => 1],
                ['name' => 'Bob', 'active' => 1, 'premium' => 0],
                ['name' => 'Charlie', 'active' => 0, 'premium' => 0],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = 'User: Alice
          Status: Active
                    Premium Member
                User: Bob
          Status: Active
                User: Charlie
              Status: Inactive
    ';

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('User: Alice', $result);
        $this->assertStringContainsString('Premium Member', $result);
        $this->assertStringContainsString('User: Bob', $result);
        $this->assertStringContainsString('User: Charlie', $result);
        $this->assertStringContainsString('Status: Inactive', $result);
    }

    public function testMixedNesting(): void
    {
        $template = '@for(orders as order)
    Order #{order.id}
    @if(order.total > 100)
        FREE SHIPPING
        @for(order.items as item)
          - {item.name}: ${item.price}
            @if(item.discount > 0)
                (Discount: {item.discount}%)
            @end
        @end
    @end
@end';

        $data = [
            'orders' => [
                [
                    'id' => '12345',
                    'total' => 150.00,
                    'items' => [
                        ['name' => 'Widget', 'price' => '50.00', 'discount' => 10],
                        ['name' => 'Gadget', 'price' => '100.00', 'discount' => 0],
                    ],
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = '    Order #12345
            FREE SHIPPING
                  - Widget: $50.00
                            (Discount: 10%)
                              - Gadget: $100.00
                        ';

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('Order #12345', $result);
        $this->assertStringContainsString('FREE SHIPPING', $result);
        $this->assertStringContainsString('Widget: $50.00', $result);
        $this->assertStringContainsString('(Discount: 10%)', $result);
        $this->assertStringContainsString('Gadget: $100.00', $result);
    }
}
