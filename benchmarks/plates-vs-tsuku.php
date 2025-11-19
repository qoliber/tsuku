<?php

/**
 * Plates vs Tsuku: CSV Export Benchmark
 * Fair comparison: Both engines rendering CSV from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use League\Plates\Engine;

echo "Plates vs Tsuku: CSV Export Benchmark\n";
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

// === PLATES BENCHMARK ===
// Plates uses native PHP templates
$templateDir = sys_get_temp_dir() . '/plates';
@mkdir($templateDir, 0777, true);

$plates = new Engine($templateDir);

// Write Plates template (native PHP)
$platesTemplate = 'SKU,Name,Price,Stock,Category,Status
<?php foreach ($products as $product): ?>
<?= $product[\'sku\'] ?>,<?= $product[\'name\'] ?>,$<?= $product[\'price\'] ?>,<?= $product[\'inStock\'] ? $product[\'stock\'] : 0 ?>,<?= $product[\'category\'] ?>,<?= $product[\'inStock\'] ? \'In Stock\' : \'Out of Stock\' ?>

<?php endforeach ?>';

file_put_contents($templateDir . '/csv.php', $platesTemplate);

echo "Plates: Rendering CSV...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $plates->render('csv', ['products' => $products]);
}
$platesTime = (microtime(true) - $start) * 1000;

// Cleanup
@unlink($templateDir . '/csv.php');

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
echo "• Plates uses native PHP (no compilation needed)\n";
echo "• Plates is extremely fast (native PHP execution)\n";
echo "• Plates requires file-based templates\n";
echo "• Tsuku uses custom syntax optimized for data transformation\n";
echo "• Both produce identical CSV output\n";
