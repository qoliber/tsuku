<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

// Example domain objects
class Order
{
    public function __construct(
        private int $id,
        private string $status,
        private array $items,
        private float $total
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}

class OrderItem
{
    public function __construct(
        private string $name,
        private int $quantity,
        private float $price
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getSubtotal(): float
    {
        return $this->quantity * $this->price;
    }
}

$tsuku = new Tsuku();

echo "=== Advanced Examples ===\n\n";

// Example 1: Complex nested object rendering
echo "1. Order Invoice Template:\n";
$template = '
===========================================
           INVOICE #{order.id}
===========================================

Status: @upper(order.status) @ternary(order.paid, "[PAID]", "[UNPAID]")

Items:
@for(order.items as item)
  {item.quantity}x {item.name} - ${item.price} = ${item.subtotal}
@end
-------------------------------------------
Total: ${order.total}

@if(order.paid)
Thank you for your payment!
@else
Payment is pending.
@end
===========================================
';

$order = new Order(
    12345,
    'paid',
    [
        new OrderItem('Widget', 2, 49.99),
        new OrderItem('Gadget', 1, 99.99),
        new OrderItem('Doohickey', 3, 29.99),
    ],
    289.94
);

$result = $tsuku->process($template, ['order' => $order]);
echo "$result\n";

// Example 2: Nested conditionals with functions
echo "\n2. User Dashboard:\n";
$template = '
@if(@length(user.notifications) > 0)
You have @length(user.notifications) notification(s):
@for(user.notifications as notification)
  - @if(notification.priority == "high")[URGENT] @end{notification.message}
@end
@else
No new notifications.
@end
';

$result = $tsuku->process($template, [
    'user' => [
        'notifications' => [
            ['priority' => 'high', 'message' => 'Security alert'],
            ['priority' => 'low', 'message' => 'New message'],
            ['priority' => 'high', 'message' => 'Payment due'],
        ],
    ],
]);
echo "$result\n";

// Example 3: Mixed data types with complex logic
echo "\n3. Product Listing with Stock Status:\n";
$template = '@for(products as product, key)
{key}. {product.name}
   Price: ${product.price}
   @if(product.stock > 10)
   Status: In Stock ({product.stock} units)
   @unless(product.onSale)
   Regular price
   @else
   ON SALE! @upper("special offer")
   @end
   @else
   @if(product.stock > 0)
   Status: Low Stock ({product.stock} units)
   @else
   Status: OUT OF STOCK
   @end
   @end

@end';

$result = $tsuku->process($template, [
    'products' => [
        [
            'name' => 'Premium Widget',
            'price' => 99.99,
            'stock' => 50,
            'onSale' => true,
        ],
        [
            'name' => 'Basic Gadget',
            'price' => 29.99,
            'stock' => 5,
            'onSale' => false,
        ],
        [
            'name' => 'Deluxe Doohickey',
            'price' => 149.99,
            'stock' => 0,
            'onSale' => false,
        ],
    ],
]);
echo "$result\n";
