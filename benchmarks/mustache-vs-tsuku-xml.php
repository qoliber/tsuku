<?php

/**
 * Mustache vs Tsuku: XML Export Benchmark
 * Fair comparison: Both engines rendering XML from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Mustache vs Tsuku: XML Export Benchmark\n";
echo "========================================\n\n";

// Generate product data
$products = [];
for ($i = 1; $i <= 1000; $i++) {
    $products[] = [
        'id' => $i,
        'sku' => 'SKU-' . str_pad((string)$i, 5, '0', STR_PAD_LEFT),
        'name' => 'Product ' . $i,
        'price' => rand(10, 1000) + (rand(0, 99) / 100),
        'stock' => rand(0, 200),
        'category' => 'Category ' . (($i % 10) + 1),
        'inStock' => rand(0, 200) > 0,
    ];
}

$data = ['products' => $products];
$iterations = 100;

echo "Dataset: 1,000 products\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<products>
@for(products as product)
  <product id="{product.id}">
    <sku>{product.sku}</sku>
    <name>@xml(product.name)</name>
    <price>@number(product.price, 2)</price>
    <stock>{product.stock}</stock>
    <category>@xml(product.category)</category>
    <inStock>@if(product.inStock)true@elsefalse@end</inStock>
  </product>
@end
</products>';

echo "Tsuku: Rendering XML...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === MUSTACHE BENCHMARK ===
$mustache = new Mustache_Engine([
    'cache' => null,
    'escape' => function($value) {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }
]);

// Mustache is logic-less - must pre-process data
$mustacheData = [
    'products' => array_map(function($p) {
        return [
            'id' => $p['id'],
            'sku' => $p['sku'],
            'name' => $p['name'],
            'price' => number_format($p['price'], 2, '.', ''),
            'stock' => $p['stock'],
            'category' => $p['category'],
            'inStock' => $p['inStock'] ? 'true' : 'false',
        ];
    }, $products)
];

$mustacheTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<products>
{{#products}}
  <product id="{{id}}">
    <sku>{{sku}}</sku>
    <name>{{name}}</name>
    <price>{{price}}</price>
    <stock>{{stock}}</stock>
    <category>{{category}}</category>
    <inStock>{{inStock}}</inStock>
  </product>
{{/products}}
</products>';

echo "Mustache: Rendering XML (with pre-processing overhead)...\n";
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
echo "• Mustache is logic-less (no conditionals/formatting)\n";
echo "• Mustache requires data pre-processing for formatting\n";
echo "• Tsuku has built-in @xml() and @if() for XML generation\n";
echo "• XML escaping configured via Mustache escape function\n";
