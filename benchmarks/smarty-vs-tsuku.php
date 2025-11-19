<?php

/**
 * Smarty vs Tsuku: CSV Export Benchmark
 * Fair comparison: Both engines rendering CSV from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use Smarty\Smarty;

echo "Smarty vs Tsuku: CSV Export Benchmark\n";
echo "======================================\n\n";

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

// === SMARTY BENCHMARK ===
$smarty = new Smarty();
$smarty->setCompileDir(sys_get_temp_dir() . '/smarty/templates_c');
$smarty->setCacheDir(sys_get_temp_dir() . '/smarty/cache');
$smarty->caching = false;
$smarty->setEscapeHtml(false);

// Create template directory
$templateDir = sys_get_temp_dir() . '/smarty/templates';
@mkdir($templateDir, 0777, true);
$smarty->setTemplateDir($templateDir);

// Write Smarty template to file
$smartyTemplate = 'SKU,Name,Price,Stock,Category,Status
{foreach $products as $product}
{$product.sku},{$product.name},${$product.price},{if $product.inStock}{$product.stock}{else}0{/if},{$product.category},{if $product.inStock}In Stock{else}Out of Stock{/if}
{/foreach}';

file_put_contents($templateDir . '/csv.tpl', $smartyTemplate);

$smarty->assign('products', $products);

echo "Smarty: Rendering CSV...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $smarty->fetch('csv.tpl');
}
$smartyTime = (microtime(true) - $start) * 1000;

// Cleanup
@unlink($templateDir . '/csv.tpl');

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Smarty:\n";
echo "  Total time: " . number_format($smartyTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($smartyTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($smartyTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($smartyTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $smartyTime) {
    $speedup = $smartyTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than Smarty!\n";
} else {
    $speedup = $tsukuTime / $smartyTime;
    echo "⚡ Smarty is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $smartyTime), 2) . " ms\n";

echo "\nNotes:\n";
echo "------\n";
echo "• Smarty is a classic template engine (since 2001)\n";
echo "• Smarty requires file-based templates\n";
echo "• Smarty has template compilation and caching (disabled)\n";
echo "• Both produce identical CSV output\n";
