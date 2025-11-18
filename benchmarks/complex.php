<?php

/**
 * Complex template benchmark
 * Tests nested loops, conditions, pattern matching, and functions
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

$template = '@for(categories as cat)
Category: @upper(cat.name)
@for(cat.products as product)
  @if(product.stock > 0)
    - {product.name} ($@number(product.price, 2))
      Status: @match(product.status)
@case("active")
✓ Available
@case("pending")
⏳ Coming Soon
@default
Unknown
@end
      @if(product.featured)
      ⭐ Featured Product
      @end
  @else
    - {product.name} (Out of Stock)
  @end
@end
@end';

$data = [
    'categories' => array_fill(0, 10, [
        'name' => 'electronics',
        'products' => array_fill(0, 20, [
            'name' => 'Premium Widget Pro',
            'price' => 1299.99,
            'stock' => 50,
            'status' => 'active',
            'featured' => true,
        ])
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

echo "Complex Template Benchmark\n";
echo "==========================\n";
echo "Template: Nested loops + conditions + match + functions\n";
echo "Data: 10 categories × 20 products = 200 items\n";
echo "Iterations: " . number_format($iterations) . "\n";
echo "Total time: " . number_format($total, 2) . " ms\n";
echo "Per iteration: " . number_format($perIteration, 4) . " ms\n";
echo "Throughput: " . number_format($iterations / ($total / 1000)) . " renders/sec\n";
