<?php

/**
 * Latte vs Tsuku: CSV Export Benchmark
 * Fair comparison: Both engines rendering CSV from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use Latte\Engine;

echo "Latte vs Tsuku: CSV Export Benchmark\n";
echo "=====================================\n\n";

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

// === LATTE BENCHMARK ===
$latte = new Engine();
$latte->setTempDirectory(sys_get_temp_dir() . '/latte');
$latte->setAutoRefresh(false);

// Use StringLoader for string-based templates
$latte->setLoader(new \Latte\Loaders\StringLoader());

$latteTemplate = 'SKU,Name,Price,Stock,Category,Status
{foreach $products as $product}
{$product[sku]},{$product[name]},${$product[price]},{if $product[inStock]}{$product[stock]}{else}0{/if},{$product[category]},{if $product[inStock]}In Stock{else}Out of Stock{/if}
{/foreach}';

echo "Latte: Rendering CSV...\n";
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
echo "• Latte is a modern template engine from Nette Framework\n";
echo "• Latte compiles to PHP and is highly optimized\n";
echo "• Latte has auto-escaping and security features\n";
echo "• Tsuku focuses on data transformation simplicity\n";
echo "• Both produce identical CSV output\n";
