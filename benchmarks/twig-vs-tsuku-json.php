<?php

/**
 * Twig vs Tsuku: JSON Export Benchmark
 * Fair comparison: Both engines rendering JSON from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

echo "Twig vs Tsuku: JSON Export Benchmark\n";
echo "=====================================\n\n";

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

// === TWIG BENCHMARK ===
$loader = new ArrayLoader([
    'json' => '{' . '"products":[
{% for product in products %}{% if loop.index > 1 %},{% endif %}
{' . '"id":{{ product.id }},' . '"sku":"{{ product.sku }}",' . '"name":{{ product.name|json_encode }},' . '"price":{{ product.price|number_format(2, \'.\', \'\') }},' . '"stock":{{ product.stock }},' . '"category":{{ product.category|json_encode }},' . '"inStock":{{ product.inStock ? "true" : "false" }}}{% endfor %}
]}'
]);

$twig = new Environment($loader, [
    'cache' => false,
    'autoescape' => false,
]);

echo "Twig: Rendering JSON...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $twig->render('json', $data);
}
$twigTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Twig:\n";
echo "  Total time: " . number_format($twigTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($twigTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($twigTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($twigTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $twigTime) {
    $speedup = $twigTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than Twig!\n";
} else {
    $speedup = $tsukuTime / $twigTime;
    echo "⚡ Twig is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $twigTime), 2) . " ms\n";

echo "\nNotes:\n";
echo "------\n";
echo "• Twig has json_encode filter for safe JSON output\n";
echo "• Tsuku has @escape(value, \"json\") function\n";
echo "• Both produce valid JSON output\n";
echo "• JSON generation is not Twig's primary use case\n";
