<?php

/**
 * Native PHP vs Tsuku: CSV Export
 * Shows performance baseline - native PHP is fastest but most verbose
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Native PHP vs Tsuku: CSV Export\n";
echo "================================\n\n";

// Generate product data
$products = [];
for ($i = 1; $i <= 10000; $i++) {
    $products[] = [
        'id' => $i,
        'sku' => 'SKU-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
        'name' => 'Product ' . $i,
        'price' => rand(999, 99999) / 100,
        'stock' => rand(0, 500),
        'category' => 'Category ' . (($i % 10) + 1),
    ];
}

$data = ['products' => $products];
$iterations = 10;

echo "Dataset: 10,000 products\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = 'ID,SKU,Name,Price,Stock,Category
@for(products as p)
{p.id},@csv(p.sku),@csv(p.name),@number(p.price, 2),{p.stock},@csv(p.category)
@end';

echo "Tsuku: Rendering CSV with template...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === NATIVE PHP BENCHMARK ===
echo "Native PHP: Generating CSV with loops...\n";

function escapeCSV($value) {
    $value = (string)$value;
    if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $output = "ID,SKU,Name,Price,Stock,Category\n";
    foreach ($products as $p) {
        $output .= $p['id'] . ',';
        $output .= escapeCSV($p['sku']) . ',';
        $output .= escapeCSV($p['name']) . ',';
        $output .= number_format($p['price'], 2, '.', '') . ',';
        $output .= $p['stock'] . ',';
        $output .= escapeCSV($p['category']) . "\n";
    }
}
$nativeTime = (microtime(true) - $start) * 1000;

// === NATIVE PHP WITH fputcsv BENCHMARK ===
echo "Native PHP (fputcsv): Using PHP's CSV writer...\n";

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['ID', 'SKU', 'Name', 'Price', 'Stock', 'Category']);
    foreach ($products as $p) {
        fputcsv($handle, [
            $p['id'],
            $p['sku'],
            $p['name'],
            number_format($p['price'], 2, '.', ''),
            $p['stock'],
            $p['category'],
        ]);
    }
    rewind($handle);
    $output = stream_get_contents($handle);
    fclose($handle);
}
$fputcsvTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku (template-based):\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per export: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000), 2) . " exports/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Native PHP (string concatenation):\n";
echo "  Total time: " . number_format($nativeTime, 2) . " ms\n";
echo "  Per export: " . number_format($nativeTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($nativeTime / 1000), 2) . " exports/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($nativeTime / 1000)) . "\n\n";

echo "Native PHP (fputcsv built-in):\n";
echo "  Total time: " . number_format($fputcsvTime, 2) . " ms\n";
echo "  Per export: " . number_format($fputcsvTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($fputcsvTime / 1000), 2) . " exports/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($fputcsvTime / 1000)) . "\n\n";

// Calculate winners
echo str_repeat('=', 60) . "\n";
echo "COMPARISON:\n";
echo str_repeat('=', 60) . "\n\n";

$fastest = min($tsukuTime, $nativeTime, $fputcsvTime);
$tsukuVsNative = $nativeTime / $tsukuTime;
$tsukuVsFputcsv = $fputcsvTime / $tsukuTime;

if ($fastest == $tsukuTime) {
    echo "🏆 Tsuku is the FASTEST!\n\n";
} elseif ($fastest == $nativeTime) {
    echo "🏆 Native PHP (concatenation) is fastest\n";
    echo "   Tsuku is " . number_format($tsukuTime / $nativeTime, 2) . "x slower\n\n";
} else {
    echo "🏆 Native PHP (fputcsv) is fastest\n";
    echo "   Tsuku is " . number_format($tsukuTime / $fputcsvTime, 2) . "x slower\n\n";
}

echo "Code Complexity Comparison:\n";
echo "----------------------------\n\n";

echo "Tsuku (3 lines):\n";
echo "  @for(products as p)\n";
echo "  {p.id},@csv(p.sku),@csv(p.name),...\n";
echo "  @end\n\n";

echo "Native PHP concatenation (~10 lines):\n";
echo "  foreach (\$products as \$p) {\n";
echo "    \$output .= \$p['id'] . ',';\n";
echo "    \$output .= escapeCSV(\$p['sku']) . ',';\n";
echo "    // ... + custom escapeCSV() function\n";
echo "  }\n\n";

echo "Native PHP fputcsv (~8 lines):\n";
echo "  \$handle = fopen('php://temp', 'r+');\n";
echo "  foreach (\$products as \$p) {\n";
echo "    fputcsv(\$handle, [\$p['id'], \$p['sku'], ...]);\n";
echo "  }\n";
echo "  // + rewind, stream_get_contents, fclose\n\n";

echo "Verdict:\n";
echo "--------\n";
echo "• Native PHP is fastest (as expected)\n";
echo "• Tsuku is " . number_format($tsukuTime / $fastest, 2) . "x slower BUT:\n";
echo "  ✓ 3x simpler code\n";
echo "  ✓ More readable/maintainable\n";
echo "  ✓ Same syntax for CSV, JSON, XML\n";
echo "  ✓ Still fast enough for production\n";
