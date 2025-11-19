<?php

/**
 * Data Transformation Benchmark: Multi-Format Export
 * Real-world scenario: Export same data to CSV, JSON, and XML
 * Shows Tsuku's flexibility with consistent syntax
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Data Transformation: Multi-Format Export\n";
echo "=========================================\n\n";

// Generate product data
$products = [];
for ($i = 1; $i <= 1000; $i++) {
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
$iterations = 50;

echo "Scenario: Multi-format product export\n";
echo "Products: " . number_format(count($products)) . "\n";
echo "Formats: CSV, JSON, XML\n";
echo "Iterations: {$iterations} per format\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();

// CSV template
$csvTemplate = 'ID,SKU,Name,Price,Stock,Category
@for(products as p)
{p.id},@csv(p.sku),@csv(p.name),@number(p.price, 2),{p.stock},@csv(p.category)
@end';

// JSON template
$jsonTemplate = '{"products":[
@for(products as p, i)@if(i > 0),@end
{"id":{p.id},"sku":"@escape(p.sku, "json")","name":"@escape(p.name, "json")","price":@number(p.price, 2),"stock":{p.stock},"category":"@escape(p.category, "json")"}@end
]}';

// XML template
$xmlTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<products>
@for(products as p)
  <product id="{p.id}">
    <sku>{p.sku}</sku>
    <name>@xml(p.name)</name>
    <price>@number(p.price, 2)</price>
    <stock>{p.stock}</stock>
    <category>@xml(p.category)</category>
  </product>
@end
</products>';

// TSV template
$tsvTemplate = 'ID\tSKU\tName\tPrice\tStock\tCategory
@for(products as p)
{p.id}\t{p.sku}\t{p.name}\t@number(p.price, 2)\t{p.stock}\t{p.category}
@end';

echo "Running benchmarks...\n\n";

// CSV Benchmark
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $csvResult = $tsuku->process($csvTemplate, $data);
}
$csvTime = (microtime(true) - $start) * 1000;

// JSON Benchmark
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $jsonResult = $tsuku->process($jsonTemplate, $data);
}
$jsonTime = (microtime(true) - $start) * 1000;

// XML Benchmark
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $xmlResult = $tsuku->process($xmlTemplate, $data);
}
$xmlTime = (microtime(true) - $start) * 1000;

// TSV Benchmark
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $tsvResult = $tsuku->process($tsvTemplate, $data);
}
$tsvTime = (microtime(true) - $start) * 1000;

// Calculate totals
$totalTime = $csvTime + $jsonTime + $xmlTime + $tsvTime;
$totalIterations = $iterations * 4;

// === RESULTS ===
echo "Results by Format:\n";
echo "==================\n\n";

echo "CSV Export:\n";
echo "  Total time: " . number_format($csvTime, 2) . " ms\n";
echo "  Per export: " . number_format($csvTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($csvTime / 1000), 2) . " exports/sec\n";
echo "  Output size: " . number_format(strlen($csvResult) / 1024, 2) . " KB\n\n";

echo "JSON Export:\n";
echo "  Total time: " . number_format($jsonTime, 2) . " ms\n";
echo "  Per export: " . number_format($jsonTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($jsonTime / 1000), 2) . " exports/sec\n";
echo "  Output size: " . number_format(strlen($jsonResult) / 1024, 2) . " KB\n";
echo "  Valid JSON: " . (json_decode($jsonResult) !== null ? 'Yes ✓' : 'No ✗') . "\n\n";

echo "XML Export:\n";
echo "  Total time: " . number_format($xmlTime, 2) . " ms\n";
echo "  Per export: " . number_format($xmlTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xmlTime / 1000), 2) . " exports/sec\n";
echo "  Output size: " . number_format(strlen($xmlResult) / 1024, 2) . " KB\n\n";

echo "TSV Export:\n";
echo "  Total time: " . number_format($tsvTime, 2) . " ms\n";
echo "  Per export: " . number_format($tsvTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsvTime / 1000), 2) . " exports/sec\n";
echo "  Output size: " . number_format(strlen($tsvResult) / 1024, 2) . " KB\n\n";

echo "Summary:\n";
echo "========\n\n";
echo "  Total exports: " . number_format($totalIterations) . " ({$iterations} × 4 formats)\n";
echo "  Total time: " . number_format($totalTime, 2) . " ms\n";
echo "  Overall throughput: " . number_format($totalIterations / ($totalTime / 1000), 2) . " exports/sec\n\n";

echo "Template Complexity (lines of code):\n";
echo "-------------------------------------\n";
echo "  CSV: " . substr_count($csvTemplate, "\n") . " lines\n";
echo "  JSON: " . substr_count($jsonTemplate, "\n") . " lines\n";
echo "  XML: " . substr_count($xmlTemplate, "\n") . " lines\n";
echo "  TSV: " . substr_count($tsvTemplate, "\n") . " lines\n";
echo "  Total: " . (substr_count($csvTemplate, "\n") + substr_count($jsonTemplate, "\n") + substr_count($xmlTemplate, "\n") + substr_count($tsvTemplate, "\n")) . " lines for 4 formats\n\n";

echo "Key Advantage:\n";
echo "--------------\n";
echo "  ✓ Same simple syntax for ALL formats\n";
echo "  ✓ No need to learn different tools\n";
echo "  ✓ Consistent escaping: @csv(), @xml(), @escape()\n";
echo "  ✓ Consistent formatting: @number(), @date()\n";
echo "  ✓ Switch formats by changing template, not code\n";
