<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

// Test class with various access patterns
class TestProduct
{
    public string $name = 'Widget';
    private string $sku = 'WDG-001';

    public function getPrice(): float
    {
        return 99.99;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function stock(): int
    {
        return 42;
    }
}

class TestUser
{
    private string $name;
    private string $email;
    private bool $active;

    public function __construct(string $name, string $email, bool $active = true)
    {
        $this->name = $name;
        $this->email = $email;
        $this->active = $active;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}

class ObjectAccessTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testArrayAccess(): void
    {
        $template = 'Product: {product.name}, Price: {product.price}';

        $data = [
            'product' => [
                'name' => 'Widget',
                'price' => 99.99,
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Product: Widget, Price: 99.99', $result);
    }

    public function testObjectPropertyAccess(): void
    {
        $template = 'Product: {product.name}';

        $product = new TestProduct();
        $data = ['product' => $product];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Product: Widget', $result);
    }

    public function testObjectMethodCall(): void
    {
        $template = 'Stock: {product.stock}';

        $product = new TestProduct();
        $data = ['product' => $product];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Stock: 42', $result);
    }

    public function testObjectGetterMethod(): void
    {
        $template = 'Price: {product.price}, SKU: {product.sku}';

        $product = new TestProduct();
        $data = ['product' => $product];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Price: 99.99, SKU: WDG-001', $result);
    }

    public function testObjectIsMethod(): void
    {
        $template = 'Available: {product.available}';

        $product = new TestProduct();
        $data = ['product' => $product];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Available: 1', $result);
    }

    public function testUserObjectWithGetters(): void
    {
        $template = 'User: {user.name} ({user.email}) - Active: {user.active}';

        $user = new TestUser('John Doe', 'john@example.com', true);
        $data = ['user' => $user];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('User: John Doe (john@example.com) - Active: 1', $result);
    }

    public function testMixedArrayAndObject(): void
    {
        $template = 'Order #{order.id}: {order.product.name} - ${order.product.price}';

        $product = new TestProduct();
        $data = [
            'order' => [
                'id' => 12345,
                'product' => $product,
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Order #12345: Widget - $99.99', $result);
    }

    public function testNestedObjectAccess(): void
    {
        $template = 'User: {user.name}, Product: {user.favorite.name}';

        $product = new TestProduct();
        $user = [
            'name' => 'Jane',
            'favorite' => $product,
        ];

        $data = ['user' => $user];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('User: Jane, Product: Widget', $result);
    }

    public function testObjectInLoop(): void
    {
        $template = '@for(products as product)
- {product.name}: ${product.price}
@end';

        $data = [
            'products' => [
                new TestProduct(),
                ['name' => 'Gadget', 'price' => 49.99],
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertStringContainsString('Widget: $99.99', $result);
        $this->assertStringContainsString('Gadget: $49.99', $result);
    }

    public function testObjectInConditional(): void
    {
        $template = '@if(product.available)
{product.name} is available!
@else
{product.name} is out of stock
@end';

        $product = new TestProduct();
        $data = ['product' => $product];

        $result = $this->tsuku->process($template, $data);
        $this->assertStringContainsString('Widget is available!', $result);
    }

    public function testObjectMethodInConditional(): void
    {
        $template = '@if(product.stock > 10)
In Stock ({product.stock} units)
@else
Low Stock
@end';

        $product = new TestProduct();
        $data = ['product' => $product];

        $result = $this->tsuku->process($template, $data);
        $this->assertStringContainsString('In Stock (42 units)', $result);
    }

    public function testMultipleUsers(): void
    {
        $template = '@for(users as user)
{user.name}: {user.email} @if(user.active)(Active)@else(Inactive)@end
@end';

        $data = [
            'users' => [
                new TestUser('Alice', 'alice@example.com', true),
                new TestUser('Bob', 'bob@example.com', false),
                new TestUser('Charlie', 'charlie@example.com', true),
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertStringContainsString('Alice: alice@example.com (Active)', $result);
        $this->assertStringContainsString('Bob: bob@example.com (Inactive)', $result);
        $this->assertStringContainsString('Charlie: charlie@example.com (Active)', $result);
    }

    public function testFallbackBehavior(): void
    {
        // Test that non-existent properties return empty string
        $template = 'Name: {product.name}, Unknown: [{product.unknown}]';

        $product = new TestProduct();
        $data = ['product' => $product];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Name: Widget, Unknown: []', $result);
    }
}
