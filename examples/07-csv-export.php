<?php

/**
 * CSV Export Examples
 *
 * Demonstrates CSV generation with proper escaping, formatting,
 * and real-world e-commerce scenarios.
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

echo "=== CSV EXPORT EXAMPLES ===\n\n";

// Example 1: Basic Product Export
echo "1. Basic Product Export\n";
echo str_repeat('-', 60) . "\n";

$template1 = 'SKU,Name,Price,Stock
@for(products as product)
@csv(product.sku),\
@csv(product.name),\
$@number(product.price, 2),\
{product.stock}
@end';

$data1 = [
    'products' => [
        ['sku' => 'WID-001', 'name' => 'Premium Widget', 'price' => 29.99, 'stock' => 100],
        ['sku' => 'GAD-002', 'name' => 'Gadget, "Professional"', 'price' => 1299.50, 'stock' => 50],
        ['sku' => 'DOO-003', 'name' => 'Doohickey Pro', 'price' => 599.00, 'stock' => 0],
    ]
];

echo $tsuku->process($template1, $data1);
echo "\n";

// Example 2: Product Export with Categories
echo "2. Product Export with Categories\n";
echo str_repeat('-', 60) . "\n";

$template2 = 'Category,SKU,Name,Description,Price,Stock,Status
@for(categories as category)
  @for(category.products as product)
@csv(category.name),\
@csv(product.sku),\
@csv(product.name),\
@csv(product.description),\
$@number(product.price, 2),\
{product.stock},\
@match(product.status) @case("active") Active @case("discontinued") Discontinued @default Unknown @end
  @end
@end';

$data2 = [
    'categories' => [
        [
            'name' => 'Electronics',
            'products' => [
                [
                    'sku' => 'ELEC-001',
                    'name' => 'Smart Phone "Pro Max"',
                    'description' => 'Latest flagship phone with amazing features, including: camera, display, and more',
                    'price' => 999.99,
                    'stock' => 25,
                    'status' => 'active'
                ],
                [
                    'sku' => 'ELEC-002',
                    'name' => 'Tablet 10"',
                    'description' => 'Perfect for work, play, and everything in between',
                    'price' => 599.00,
                    'stock' => 0,
                    'status' => 'discontinued'
                ],
            ]
        ],
        [
            'name' => 'Home & Garden',
            'products' => [
                [
                    'sku' => 'HOME-001',
                    'name' => 'Garden Hose, 50ft',
                    'description' => 'Durable, kink-resistant garden hose',
                    'price' => 49.99,
                    'stock' => 100,
                    'status' => 'active'
                ],
            ]
        ]
    ]
];

echo $tsuku->process($template2, $data2);
echo "\n";

// Example 3: Customer Order Export
echo "3. Customer Order Export\n";
echo str_repeat('-', 60) . "\n";

$template3 = 'Order ID,Date,Customer Name,Customer Email,Product,Quantity,Unit Price,Total,Status
@for(orders as order)
  @for(order.items as item)
{order.id},\
@date("Y-m-d", order.date),\
@csv(order.customer.name),\
@csv(order.customer.email),\
@csv(item.product),\
{item.quantity},\
$@number(item.price, 2),\
$@number(item.quantity * item.price, 2),\
{order.status}
  @end
@end';

$data3 = [
    'orders' => [
        [
            'id' => 'ORD-1001',
            'date' => strtotime('2025-01-15'),
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com'
            ],
            'items' => [
                ['product' => 'Widget Pro', 'quantity' => 2, 'price' => 29.99],
                ['product' => 'Gadget "Premium"', 'quantity' => 1, 'price' => 59.99],
            ],
            'status' => 'completed'
        ],
        [
            'id' => 'ORD-1002',
            'date' => strtotime('2025-01-16'),
            'customer' => [
                'name' => 'Jane Smith, Jr.',
                'email' => 'jane.smith@example.com'
            ],
            'items' => [
                ['product' => 'Doohickey', 'quantity' => 5, 'price' => 9.99],
            ],
            'status' => 'pending'
        ],
    ]
];

echo $tsuku->process($template3, $data3);
echo "\n";

// Example 4: Inventory Report with Conditionals
echo "4. Inventory Report with Low Stock Alerts\n";
echo str_repeat('-', 60) . "\n";

$template4 = 'SKU,Product,Current Stock,Reorder Point,Alert
@for(inventory as item)
@csv(item.sku),\
@csv(item.name),\
{item.stock},\
{item.reorder_point},\
@if(item.stock <= item.reorder_point)
LOW STOCK - REORDER NOW
@else
OK
@end
@end';

$data4 = [
    'inventory' => [
        ['sku' => 'WID-001', 'name' => 'Widget A', 'stock' => 5, 'reorder_point' => 10],
        ['sku' => 'GAD-002', 'name' => 'Gadget B', 'stock' => 50, 'reorder_point' => 20],
        ['sku' => 'DOO-003', 'name' => 'Doohickey C', 'stock' => 0, 'reorder_point' => 5],
    ]
];

echo $tsuku->process($template4, $data4);
echo "\n";

// Example 5: Sales Report with Calculations
echo "5. Sales Report with Calculations\n";
echo str_repeat('-', 60) . "\n";

$template5 = 'Product,Units Sold,Unit Price,Revenue,Profit Margin
@for(sales as item)
@csv(item.product),\
{item.units_sold},\
$@number(item.unit_price, 2),\
$@number(item.units_sold * item.unit_price, 2),\
@number((item.unit_price - item.cost) / item.unit_price * 100, 1)%
@end';

$data5 = [
    'sales' => [
        ['product' => 'Premium Widget', 'units_sold' => 150, 'unit_price' => 29.99, 'cost' => 15.00],
        ['product' => 'Gadget Pro', 'units_sold' => 75, 'unit_price' => 59.99, 'cost' => 30.00],
        ['product' => 'Doohickey Basic', 'units_sold' => 500, 'unit_price' => 9.99, 'cost' => 5.00],
    ]
];

echo $tsuku->process($template5, $data5);
echo "\n";

echo "=== All CSV examples completed! ===\n";
