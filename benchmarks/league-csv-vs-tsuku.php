<?php

/**
 * League CSV vs Tsuku: CSV Export
 * Comparison with specialized CSV library
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use League\Csv\Writer;

echo "League CSV vs Tsuku: CSV Export\n";
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

echo "Tsuku: Template-based CSV generation...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === LEAGUE CSV BENCHMARK ===
echo "League CSV: Specialized CSV library...\n";

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $csv = Writer::createFromString();
    $csv->insertOne(['ID', 'SKU', 'Name', 'Price', 'Stock', 'Category']);
    foreach ($products as $p) {
        $csv->insertOne([
            $p['id'],
            $p['sku'],
            $p['name'],
            number_format($p['price'], 2, '.', ''),
            $p['stock'],
            $p['category'],
        ]);
    }
    $result = $csv->toString();
}
$leagueTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per export: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000), 2) . " exports/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "League CSV:\n";
echo "  Total time: " . number_format($leagueTime, 2) . " ms\n";
echo "  Per export: " . number_format($leagueTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($leagueTime / 1000), 2) . " exports/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($leagueTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $leagueTime) {
    $speedup = $leagueTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than League CSV!\n";
} else {
    $speedup = $tsukuTime / $leagueTime;
    echo "⚡ League CSV is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $leagueTime), 2) . " ms\n\n";

echo "Code Comparison:\n";
echo "----------------\n\n";

echo "Tsuku (3 lines - works for CSV, JSON, XML):\n";
echo "  \$template = 'ID,SKU,Name,...\n";
echo "  @for(products as p)\n";
echo "  {p.id},@csv(p.sku),@csv(p.name),...\n";
echo "  @end';\n";
echo "  \$output = \$tsuku->process(\$template, \$data);\n\n";

echo "League CSV (~7 lines - CSV only):\n";
echo "  \$csv = Writer::createFromString();\n";
echo "  \$csv->insertOne(['ID', 'SKU', 'Name', ...]);\n";
echo "  foreach (\$products as \$p) {\n";
echo "    \$csv->insertOne([\$p['id'], \$p['sku'], ...]);\n";
echo "  }\n";
echo "  \$output = \$csv->toString();\n\n";

echo "Key Differences:\n";
echo "----------------\n";
echo "League CSV:\n";
echo "  ✓ Specialized for CSV (very good at one thing)\n";
echo "  ✓ Rich CSV features (filtering, sorting, etc.)\n";
echo "  ✓ Battle-tested library\n";
echo "  ✗ Only does CSV (need different library for JSON/XML)\n";
echo "  ✗ More verbose for simple exports\n\n";

echo "Tsuku:\n";
echo "  ✓ Works for CSV, JSON, XML, TSV with same syntax\n";
echo "  ✓ Simpler for straightforward exports\n";
echo "  ✓ Template-based (readable, maintainable)\n";
echo "  ✗ Less specialized CSV features\n\n";

echo "Verdict:\n";
echo "--------\n";
echo "• Choose League CSV if: You need advanced CSV features\n";
echo "• Choose Tsuku if: You need multiple formats with simple syntax\n";
