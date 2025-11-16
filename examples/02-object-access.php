<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

// Example classes to demonstrate object access
class Product
{
    public string $name = 'Super Widget';
    private float $price = 99.99;
    private int $stock = 42;

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function isAvailable(): bool
    {
        return $this->stock > 0;
    }
}

class User
{
    public function __construct(
        private string $name,
        private string $email,
        private bool $active = true
    ) {
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

$tsuku = new Tsuku();

echo "=== Smart Object/Array Access ===\n\n";

// Example 1: Object with getter methods
echo "1. Object with Getter Methods:\n";
$template = 'Product: {product.name}, Price: ${product.price}, Stock: {product.stock}';
$result = $tsuku->process($template, ['product' => new Product()]);
echo "$result\n\n";

// Example 2: Array access (same syntax!)
echo "2. Array Access (same syntax):\n";
$template = 'Item: {item.name}, Quantity: {item.qty}';
$result = $tsuku->process($template, ['item' => ['name' => 'Gadget', 'qty' => 10]]);
echo "$result\n\n";

// Example 3: Mixed array and object
echo "3. Mixed Array and Object:\n";
$template = 'Order #{order.id}: {order.product.name} - ${order.product.price}';
$result = $tsuku->process($template, [
    'order' => [
        'id' => 12345,
        'product' => new Product(),
    ],
]);
echo "$result\n\n";

// Example 4: Object in conditional (calls isAvailable() method)
echo "4. Object Method in Conditional:\n";
$template = '@if(product.available)
{product.name} is IN STOCK ({product.stock} units)
@else
{product.name} is OUT OF STOCK
@end';
$result = $tsuku->process($template, ['product' => new Product()]);
echo "$result\n";

// Example 5: Objects in loop
echo "5. Objects in Loop:\n";
$template = '@for(users as user)
- {user.name} <{user.email}> @if(user.active)[ACTIVE]@else[INACTIVE]@end
@end';
$result = $tsuku->process($template, [
    'users' => [
        new User('Alice', 'alice@example.com', true),
        new User('Bob', 'bob@example.com', false),
        new User('Charlie', 'charlie@example.com', true),
    ],
]);
echo "$result\n";

echo "\n=== Access Strategy Priority ===\n";
echo "1. Array access: \$data['key']\n";
echo "2. Getter methods: \$object->getKey()\n";
echo "3. Boolean getters: \$object->isKey()\n";
echo "4. Direct methods: \$object->key()\n";
echo "5. Public properties: \$object->key\n";
