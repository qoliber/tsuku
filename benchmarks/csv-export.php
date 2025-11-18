<?php

/**
 * Real-world CSV export benchmark
 * Tests realistic e-commerce product export scenario
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

$template = 'SKU,Name,Description,Price,Stock,Status
@for(products as product)
@csv(product.sku),@csv(product.name),@csv(product.description),$@number(product.price, 2),{product.stock},@match(product.status)
@case("active", "in_stock")
Available
@case("discontinued")
Discontinued
@default
Unknown
@end
@end';

$data = [
    'products' => array_fill(0, 1000, [
        'sku' => 'WID-001-PRO-EDITION',
        'name' => 'Premium Widget "Pro Edition"',
        'description' => 'A premium widget with advanced features, perfect for professionals',
        'price' => 1299.99,
        'stock' => 50,
        'status' => 'active',
    ])
];

$iterations = 100;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($template, $data);
}
$end = microtime(true);

$total = ($end - $start) * 1000;
$perIteration = $total / $iterations;
$productsPerSecond = (1000 * $iterations) / ($total / 1000);

echo "CSV Export Benchmark (Real-World)\n";
echo "==================================\n";
echo "Scenario: E-commerce product export\n";
echo "Template: CSV with escaping, formatting, pattern matching\n";
echo "Products per export: 1,000\n";
echo "Iterations: " . number_format($iterations) . "\n";
echo "Total time: " . number_format($total, 2) . " ms\n";
echo "Per iteration: " . number_format($perIteration, 4) . " ms\n";
echo "Throughput: " . number_format($iterations / ($total / 1000)) . " exports/sec\n";
echo "Products/sec: " . number_format($productsPerSecond) . " products/sec\n";
