<?php

/**
 * Smarty vs Tsuku: JSON Export Benchmark
 * Fair comparison: Both engines rendering JSON from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use Smarty\Smarty;

echo "Smarty vs Tsuku: JSON Export Benchmark\n";
echo "=======================================\n\n";

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

// === SMARTY BENCHMARK ===
$smarty = new Smarty();
$smarty->setCompileDir(sys_get_temp_dir() . '/smarty/templates_c');
$smarty->setCacheDir(sys_get_temp_dir() . '/smarty/cache');
$smarty->caching = false;
$smarty->setEscapeHtml(false);

$templateDir = sys_get_temp_dir() . '/smarty/templates';
@mkdir($templateDir, 0777, true);
$smarty->setTemplateDir($templateDir);

$smartyTemplate = '{ldelim}"products":[
{foreach $products as $index => $product}{if $index > 0},{/if}
{ldelim}"id":{$product.id},"sku":"{$product.sku}","name":{$product.name|json_encode},"price":{$product.price|string_format:"%.2f"},"stock":{$product.stock},"category":{$product.category|json_encode},"inStock":{if $product.inStock}true{else}false{/if}{rdelim}{/foreach}
]}';

file_put_contents($templateDir . '/json.tpl', $smartyTemplate);

$smarty->assign('products', $products);

echo "Smarty: Rendering JSON...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $smarty->fetch('json.tpl');
}
$smartyTime = (microtime(true) - $start) * 1000;

// Cleanup
@unlink($templateDir . '/json.tpl');

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
echo "• Smarty has json_encode modifier for JSON output\n";
echo "• Smarty requires file-based templates\n";
echo "• Tsuku is string-based (no file system needed)\n";
echo "• JSON generation is not Smarty's primary use case\n";
