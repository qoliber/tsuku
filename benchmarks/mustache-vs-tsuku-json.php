<?php

/**
 * Mustache vs Tsuku: JSON Export Benchmark
 * Fair comparison: Both engines rendering JSON from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Mustache vs Tsuku: JSON Export Benchmark\n";
echo "=========================================\n\n";

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
$tsukuTemplate = '{"products":[
@for(products as product, index)@if(index > 0),@end
{"id":{product.id},"sku":"{product.sku}","name":"@escape(product.name, "json")","price":@number(product.price, 2),"stock":{product.stock},"category":"@escape(product.category, "json")","inStock":@if(product.inStock)true@elsefalse@end}@end
]}';

echo "Tsuku: Rendering JSON...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === MUSTACHE BENCHMARK ===
$mustache = new Mustache_Engine([
    'cache' => null,
    'escape' => function($value) {
        return $value; // No auto-escaping
    }
]);

// Mustache is logic-less - must pre-process data for JSON
$mustacheData = [
    'products' => array_map(function($p, $i) use ($products) {
        return [
            'id' => $p['id'],
            'sku' => $p['sku'],
            'name' => json_encode($p['name']),
            'price' => number_format($p['price'], 2, '.', ''),
            'stock' => $p['stock'],
            'category' => json_encode($p['category']),
            'inStock' => $p['inStock'] ? 'true' : 'false',
            'notLast' => $i < count($products) - 1,
        ];
    }, $products, array_keys($products))
];

$mustacheTemplate = '{"products":[
{{#products}}
{"id":{{id}},"sku":"{{sku}}","name":{{name}},"price":{{price}},"stock":{{stock}},"category":{{category}},"inStock":{{inStock}}}{{#notLast}},{{/notLast}}
{{/products}}
]}';

echo "Mustache: Rendering JSON (with pre-processing overhead)...\n";
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
echo "• Mustache requires significant data pre-processing for JSON\n";
echo "• Tsuku has built-in @escape() and @if() for JSON generation\n";
echo "• JSON generation is awkward in logic-less templates\n";
