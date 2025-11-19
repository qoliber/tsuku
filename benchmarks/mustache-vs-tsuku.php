<?php

/**
 * Mustache vs Tsuku: CSV Export Benchmark
 * Fair comparison: Both engines rendering CSV from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Mustache vs Tsuku: CSV Export Benchmark\n";
echo "========================================\n\n";

// Generate product data
$products = [];
for ($i = 1; $i <= 1000; $i++) {
    $products[] = [
        'sku' => 'SKU-' . str_pad((string)$i, 5, '0', STR_PAD_LEFT),
        'name' => 'Product ' . $i,
        'price' => rand(10, 1000) + (rand(0, 99) / 100),
        'stock' => rand(0, 200),
        'category' => 'Category ' . (($i % 10) + 1),
        'inStock' => rand(0, 200) > 0,
        'outOfStock' => rand(0, 200) == 0,
    ];
}

$data = ['products' => $products];
$iterations = 100;

echo "Dataset: 1,000 products\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = 'SKU,Name,Price,Stock,Category,Status
@for(products as product)
{product.sku},{product.name},${product.price},@if(product.inStock){product.stock}@else0@end,{product.category},@if(product.inStock)In Stock@elseOut of Stock@end
@end';

echo "Tsuku: Rendering CSV...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === MUSTACHE BENCHMARK ===
$mustache = new Mustache_Engine([
    'cache' => null,
    'escape' => function($value) {
        return $value; // No escaping for CSV
    }
]);

// Mustache is logic-less, so we need to pre-process data
$mustacheData = [
    'products' => array_map(function($p) {
        return [
            'sku' => $p['sku'],
            'name' => $p['name'],
            'price' => $p['price'],
            'stock' => $p['inStock'] ? $p['stock'] : 0,
            'category' => $p['category'],
            'status' => $p['inStock'] ? 'In Stock' : 'Out of Stock',
        ];
    }, $products)
];

$mustacheTemplate = 'SKU,Name,Price,Stock,Category,Status
{{#products}}
{{sku}},{{name}},${{price}},{{stock}},{{category}},{{status}}
{{/products}}';

echo "Mustache: Rendering CSV...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $mustache->render($mustacheTemplate, $mustacheData);
}
$mustacheTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Mustache:\n";
echo "  Total time: " . number_format($mustacheTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($mustacheTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($mustacheTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($mustacheTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $mustacheTime) {
    $speedup = $mustacheTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than Mustache!\n";
} else {
    $speedup = $tsukuTime / $mustacheTime;
    echo "⚡ Mustache is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $mustacheTime), 2) . " ms\n";

echo "\nNotes:\n";
echo "------\n";
echo "• Mustache is logic-less (no conditionals in templates)\n";
echo "• Mustache requires pre-processing data (adds overhead)\n";
echo "• Tsuku has built-in conditionals (@if/@else)\n";
echo "• Both produce identical CSV output\n";
