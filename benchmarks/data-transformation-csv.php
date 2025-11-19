<?php

/**
 * Data Transformation Benchmark: CSV Export
 * Real-world scenario: E-commerce product catalog export
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Data Transformation: CSV Export\n";
echo "================================\n\n";

// Generate realistic e-commerce data
$products = [];
$categories = ['Electronics', 'Clothing', 'Home & Garden', 'Sports', 'Books', 'Toys', 'Food', 'Beauty', 'Automotive', 'Pet Supplies'];

for ($i = 1; $i <= 10000; $i++) {
    $products[] = [
        'id' => $i,
        'sku' => 'SKU-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
        'name' => 'Product ' . $i,
        'description' => 'High quality product with excellent features and specifications. Perfect for your needs.',
        'category' => $categories[($i - 1) % count($categories)],
        'price' => rand(999, 99999) / 100,
        'comparePrice' => rand(100000, 150000) / 100,
        'cost' => rand(500, 50000) / 100,
        'stock' => rand(0, 500),
        'weight' => rand(100, 10000) / 100,
        'ean' => str_pad((string)rand(1000000000000, 9999999999999), 13, '0', STR_PAD_LEFT),
        'manufacturerSku' => 'MFG-' . rand(10000, 99999),
        'inStock' => rand(0, 500) > 0,
        'featured' => rand(0, 10) > 7,
        'onSale' => rand(0, 10) > 6,
    ];
}

$data = ['products' => $products];
$iterations = 10;

echo "Scenario: E-commerce catalog export\n";
echo "Products: " . number_format(count($products)) . "\n";
echo "Fields per product: 15\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();

// CSV template with proper escaping
$template = 'ID,SKU,Name,Description,Category,Price,Compare Price,Cost,Margin %,Stock,Weight,EAN,Manufacturer SKU,In Stock,Featured,On Sale
@for(products as p)
{p.id},@csv(p.sku),@csv(p.name),@csv(p.description),@csv(p.category),@number(p.price, 2),@number(p.comparePrice, 2),@number(p.cost, 2),@number((p.price - p.cost) / p.price * 100, 1),{p.stock},@number(p.weight, 2),{p.ean},@csv(p.manufacturerSku),@if(p.inStock)Yes@elseNo@end,@if(p.featured)Yes@elseNo@end,@if(p.onSale)Yes@elseNo@end
@end';

echo "Running benchmark...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($template, $data);
}
$totalTime = (microtime(true) - $start) * 1000;

// Calculate metrics
$avgTime = $totalTime / $iterations;
$throughput = $iterations / ($totalTime / 1000);
$productsPerSec = (count($products) * $iterations) / ($totalTime / 1000);
$rowsPerSec = $productsPerSec + $iterations; // +1 for header per iteration
$outputSize = strlen($result);

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Performance:\n";
echo "  Total time: " . number_format($totalTime, 2) . " ms\n";
echo "  Avg per export: " . number_format($avgTime, 2) . " ms\n";
echo "  Throughput: " . number_format($throughput, 2) . " exports/sec\n";
echo "  Products/sec: " . number_format($productsPerSec, 0) . "\n";
echo "  Rows/sec: " . number_format($rowsPerSec, 0) . "\n\n";

echo "Output:\n";
echo "  File size: " . number_format($outputSize / 1024, 2) . " KB\n";
echo "  Rows: " . number_format(count($products) + 1) . " (including header)\n";
echo "  Columns: 16\n\n";

echo "Real-World Scenarios:\n";
echo "---------------------\n";
echo "  Daily export (10K products): " . number_format($avgTime, 1) . " ms ✓\n";
echo "  Hourly sync (10K products): " . number_format($avgTime, 1) . " ms ✓\n";
echo "  On-demand download: " . number_format($avgTime, 1) . " ms ✓\n\n";

echo "Sample Output (first 5 rows):\n";
echo "------------------------------\n";
$lines = explode("\n", $result);
for ($i = 0; $i < min(6, count($lines)); $i++) {
    echo $lines[$i] . "\n";
}
if (count($lines) > 6) {
    echo "... (" . number_format(count($lines) - 6) . " more rows)\n";
}
