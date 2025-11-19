<?php

/**
 * Latte vs Tsuku: XML Export Benchmark
 * Fair comparison: Both engines rendering XML from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use Latte\Engine;

echo "Latte vs Tsuku: XML Export Benchmark\n";
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

// === LATTE BENCHMARK ===
$latte = new Engine();
$latte->setTempDirectory(sys_get_temp_dir() . '/latte');
$latte->setAutoRefresh(false);
$latte->setLoader(new \Latte\Loaders\StringLoader());

$latteTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<products>
{foreach $products as $product}
  <product id="{$product[id]}">
    <sku>{$product[sku]}</sku>
    <name>{$product[name]}</name>
    <price>{$product[price]|number:2}</price>
    <stock>{$product[stock]}</stock>
    <category>{$product[category]}</category>
    <inStock>{if $product[inStock]}true{else}false{/if}</inStock>
  </product>
{/foreach}
</products>';

echo "Latte: Rendering XML...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $latte->renderToString($latteTemplate, $data);
}
$latteTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Latte:\n";
echo "  Total time: " . number_format($latteTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($latteTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($latteTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($latteTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $latteTime) {
    $speedup = $latteTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than Latte!\n";
} else {
    $speedup = $tsukuTime / $latteTime;
    echo "⚡ Latte is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $latteTime), 2) . " ms\n";

echo "\nNotes:\n";
echo "------\n";
echo "• Latte has auto-escaping for XML (context-aware)\n";
echo "• Latte compiles to PHP and is highly optimized\n";
echo "• Tsuku is string-based (no file system needed)\n";
echo "• XML generation is not Latte's primary use case\n";
