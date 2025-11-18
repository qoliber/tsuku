<?php

/**
 * Simple template benchmark
 * Tests basic variable rendering and loops
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

$template = '@for(products as product)
{product.sku},{product.name},$@number(product.price, 2),{product.stock}
@end';

$data = [
    'products' => array_fill(0, 100, [
        'sku' => 'WID-001',
        'name' => 'Premium Widget',
        'price' => 1299.99,
        'stock' => 50,
    ])
];

$iterations = 1000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($template, $data);
}
$end = microtime(true);

$total = ($end - $start) * 1000;
$perIteration = $total / $iterations;

echo "Simple Template Benchmark\n";
echo "========================\n";
echo "Template: CSV export with 100 products\n";
echo "Iterations: " . number_format($iterations) . "\n";
echo "Total time: " . number_format($total, 2) . " ms\n";
echo "Per iteration: " . number_format($perIteration, 4) . " ms\n";
echo "Throughput: " . number_format($iterations / ($total / 1000)) . " renders/sec\n";
