<?php

/**
 * Plates vs Tsuku: XML Export Benchmark
 * Fair comparison: Both engines rendering XML from product data
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use League\Plates\Engine;

echo "Plates vs Tsuku: XML Export Benchmark\n";
echo "======================================\n\n";

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

// === PLATES BENCHMARK ===
$templateDir = sys_get_temp_dir() . '/plates';
@mkdir($templateDir, 0777, true);

$plates = new Engine($templateDir);

// Plates uses native PHP
$platesTemplate = '<?php echo \'<?xml version="1.0" encoding="UTF-8"?>\' . "\\n"; ?>
<products>
<?php foreach ($products as $product): ?>
  <product id="<?= $product[\'id\'] ?>">
    <sku><?= $product[\'sku\'] ?></sku>
    <name><?= htmlspecialchars($product[\'name\'], ENT_XML1, \'UTF-8\') ?></name>
    <price><?= number_format($product[\'price\'], 2, \'.\', \'\') ?></price>
    <stock><?= $product[\'stock\'] ?></stock>
    <category><?= htmlspecialchars($product[\'category\'], ENT_XML1, \'UTF-8\') ?></category>
    <inStock><?= $product[\'inStock\'] ? \'true\' : \'false\' ?></inStock>
  </product>
<?php endforeach ?>
</products>';

file_put_contents($templateDir . '/xml.php', $platesTemplate);

echo "Plates: Rendering XML...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $plates->render('xml', ['products' => $products]);
}
$platesTime = (microtime(true) - $start) * 1000;

// Cleanup
@unlink($templateDir . '/xml.php');

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
echo "• Native PHP htmlspecialchars() is very efficient\n";
