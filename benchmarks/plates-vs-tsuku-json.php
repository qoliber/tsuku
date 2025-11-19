<?php

/**
 * Plates vs Tsuku: JSON Export Benchmark
 * Fair comparison: Both engines rendering JSON from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use League\Plates\Engine;

echo "Plates vs Tsuku: JSON Export Benchmark\n";
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

// === PLATES BENCHMARK ===
$templateDir = sys_get_temp_dir() . '/plates';
@mkdir($templateDir, 0777, true);

$plates = new Engine($templateDir);

// Plates uses native PHP
$platesTemplate = '{"products":[
<?php foreach ($products as $index => $product): ?><?php if ($index > 0): ?>,<?php endif ?>
{"id":<?= $product[\'id\'] ?>,"sku":"<?= $product[\'sku\'] ?>","name":<?= json_encode($product[\'name\']) ?>,"price":<?= number_format($product[\'price\'], 2, \'.\', \'\') ?>,"stock":<?= $product[\'stock\'] ?>,"category":<?= json_encode($product[\'category\']) ?>,"inStock":<?= $product[\'inStock\'] ? \'true\' : \'false\' ?>}<?php endforeach ?>
]}';

file_put_contents($templateDir . '/json.php', $platesTemplate);

echo "Plates: Rendering JSON...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $plates->render('json', ['products' => $products]);
}
$platesTime = (microtime(true) - $start) * 1000;

// Cleanup
@unlink($templateDir . '/json.php');

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Plates:\n";
echo "  Total time: " . number_format($platesTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($platesTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($platesTime / 1000)) . " renders/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($platesTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $platesTime) {
    $speedup = $platesTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than Plates!\n";
} else {
    $speedup = $tsukuTime / $platesTime;
    echo "⚡ Plates is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $platesTime), 2) . " ms\n";

echo "\nNotes:\n";
echo "------\n";
echo "• Plates uses native PHP (extremely fast)\n";
echo "• Plates requires file-based templates\n";
echo "• Tsuku is string-based (no file system needed)\n";
echo "• Native PHP json_encode() is very efficient\n";
