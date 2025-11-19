<?php

/**
 * Large dataset benchmark with nested loops and conditionals
 * Tests performance with realistic e-commerce scenario:
 * - 50 categories
 * - 100 products per category = 5,000 total products
 * - Nested @for loops
 * - Multiple @if conditions
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Large Nested Dataset Benchmark\n";
echo "===============================\n\n";

// Generate realistic e-commerce data
$categories = [];
for ($c = 0; $c < 50; $c++) {
    $categoryName = 'Category-' . ($c + 1);
    $products = [];

    for ($p = 0; $p < 100; $p++) {
        $products[] = [
            'id' => ($c * 100) + $p + 1,
            'sku' => sprintf('SKU-%04d-%04d', $c + 1, $p + 1),
            'name' => 'Product ' . (($c * 100) + $p + 1),
            'price' => rand(999, 99999) / 100, // $9.99 to $999.99
            'stock' => rand(0, 200),
            'onSale' => (bool)rand(0, 1),
            'discount' => rand(0, 50),
            'featured' => (bool)rand(0, 4), // ~20% featured
            'rating' => rand(1, 5),
        ];
    }

    $categories[] = [
        'id' => $c + 1,
        'name' => $categoryName,
        'active' => (bool)rand(0, 9), // ~90% active
        'products' => $products,
    ];
}

$totalProducts = 50 * 100; // 5,000 products

// Complex template with nested loops and multiple conditionals
$template = 'E-COMMERCE PRODUCT CATALOG
==========================

Total Categories: {stats.categoryCount}
Total Products: {stats.productCount}

@for(categories as cat)
@if(cat.active)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CATEGORY: @upper(cat.name) (ID: {cat.id})
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

@for(cat.products as product)
@if(product.featured)
⭐ FEATURED: {product.name}
@else
   {product.name}
@end
   SKU: {product.sku} | ID: {product.id}
   @if(product.onSale)
   💰 SALE PRICE: $@number(product.price, 2) (@number(product.discount, 0)% OFF)
   @else
   Price: $@number(product.price, 2)
   @end
   @if(product.stock > 100)
   Stock: {product.stock} ✓ IN STOCK (High)
   @else
   @if(product.stock > 0)
   Stock: {product.stock} ⚠ LOW STOCK
   @else
   Stock: OUT OF STOCK ✗
   @end
   @end
   Rating: @for(ratings as r)@if(r <= product.rating)★@else☆@end@end ({product.rating}/5)
   ---

@end
@end
@end

End of Catalog';

$data = [
    'stats' => [
        'categoryCount' => count($categories),
        'productCount' => $totalProducts,
    ],
    'categories' => $categories,
    'ratings' => [1, 2, 3, 4, 5], // For star rendering
];

// Benchmark
$tsuku = new Tsuku();
$iterations = 10; // Fewer iterations due to large dataset

echo "Dataset:\n";
echo "  Categories: " . count($categories) . "\n";
echo "  Products per category: 100\n";
echo "  Total products: " . number_format($totalProducts) . "\n";
echo "  Nested loops: 3 levels deep\n";
echo "  Conditionals: 6 @if checks per product\n";
echo "\n";

echo "Running benchmark...\n";

$memoryBefore = memory_get_usage();

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($template, $data);
}
$end = microtime(true);

$memoryAfter = memory_get_usage();
$memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024;

$total = ($end - $start) * 1000;
$perIteration = $total / $iterations;
$outputSize = strlen($result);

echo "\n";
echo "Results:\n";
echo "========\n";
echo "Iterations: " . number_format($iterations) . "\n";
echo "Total time: " . number_format($total, 2) . " ms\n";
echo "Per iteration: " . number_format($perIteration, 2) . " ms\n";
echo "Throughput: " . number_format($iterations / ($total / 1000), 2) . " renders/sec\n";
echo "\n";
echo "Per Product:\n";
echo "  Time per product: " . number_format(($perIteration / $totalProducts) * 1000, 2) . " μs\n";
echo "  Products processed/sec: " . number_format(($totalProducts * $iterations) / ($total / 1000)) . "\n";
echo "\n";
echo "Output:\n";
echo "  Size per render: " . number_format($outputSize / 1024, 2) . " KB\n";
echo "  Lines generated: ~" . number_format(substr_count($result, "\n")) . "\n";
echo "\n";
echo "Memory:\n";
echo "  Memory used: " . number_format($memoryUsed, 2) . " MB\n";
echo "  Memory per product: " . number_format(($memoryUsed * 1024) / $totalProducts, 2) . " KB\n";
echo "\n";

// Show sample output (first 50 lines)
echo "Sample Output (first 50 lines):\n";
echo "================================\n";
$lines = explode("\n", $result);
echo implode("\n", array_slice($lines, 0, 50));
echo "\n... (truncated)\n";
