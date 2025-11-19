<?php

/**
 * Data Transformation Benchmark: XML Product Feed
 * Real-world scenario: Google Shopping / Facebook Catalog feed
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Data Transformation: XML Product Feed\n";
echo "======================================\n\n";

// Generate realistic product feed data
$products = [];
for ($i = 1; $i <= 5000; $i++) {
    $products[] = [
        'id' => $i,
        'title' => 'Premium Quality Product ' . $i . ' - Special Edition',
        'description' => 'High quality product with excellent features and specifications. Perfect for your needs. Limited time offer!',
        'link' => 'https://example.com/products/product-' . $i,
        'imageLink' => 'https://example.com/images/product-' . $i . '.jpg',
        'price' => rand(999, 99999) / 100,
        'availability' => rand(0, 100) > 10 ? 'in stock' : 'out of stock',
        'brand' => 'Brand ' . (($i % 20) + 1),
        'gtin' => str_pad((string)rand(1000000000000, 9999999999999), 13, '0', STR_PAD_LEFT),
        'mpn' => 'MPN-' . str_pad((string)$i, 8, '0', STR_PAD_LEFT),
        'condition' => 'new',
        'googleProductCategory' => 'Electronics > Computers > Laptops',
        'productType' => 'Electronics > Computers > Laptops > Gaming Laptops',
        'shipping' => [
            'country' => 'US',
            'service' => 'Standard',
            'price' => '9.99',
        ],
    ];
}

$data = [
    'products' => $products,
    'title' => 'My Product Feed',
    'link' => 'https://example.com',
    'description' => 'Product catalog for example.com',
];

$iterations = 10;

echo "Scenario: Google Shopping XML feed\n";
echo "Products: " . number_format(count($products)) . "\n";
echo "Fields per product: 14\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();

// Google Shopping XML feed template
$template = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title>@xml(title)</title>
    <link>@xml(link)</link>
    <description>@xml(description)</description>
@for(products as product)
    <item>
      <g:id>{product.id}</g:id>
      <g:title>@xml(product.title)</g:title>
      <g:description>@xml(product.description)</g:description>
      <g:link>@xml(product.link)</g:link>
      <g:image_link>@xml(product.imageLink)</g:image_link>
      <g:price>@number(product.price, 2) USD</g:price>
      <g:availability>@xml(product.availability)</g:availability>
      <g:brand>@xml(product.brand)</g:brand>
      <g:gtin>{product.gtin}</g:gtin>
      <g:mpn>@xml(product.mpn)</g:mpn>
      <g:condition>@xml(product.condition)</g:condition>
      <g:google_product_category>@xml(product.googleProductCategory)</g:google_product_category>
      <g:product_type>@xml(product.productType)</g:product_type>
      <g:shipping>
        <g:country>@xml(product.shipping.country)</g:country>
        <g:service>@xml(product.shipping.service)</g:service>
        <g:price>@xml(product.shipping.price) USD</g:price>
      </g:shipping>
    </item>
@end
  </channel>
</rss>';

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
$outputSize = strlen($result);

// Validate XML
$isValidXml = false;
$prevErrors = libxml_use_internal_errors(true);
$xml = simplexml_load_string($result);
$isValidXml = $xml !== false;
libxml_clear_errors();
libxml_use_internal_errors($prevErrors);

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Performance:\n";
echo "  Total time: " . number_format($totalTime, 2) . " ms\n";
echo "  Avg per feed: " . number_format($avgTime, 2) . " ms\n";
echo "  Throughput: " . number_format($throughput, 2) . " feeds/sec\n";
echo "  Products/sec: " . number_format($productsPerSec, 0) . "\n\n";

echo "Output:\n";
echo "  Feed size: " . number_format($outputSize / 1024, 2) . " KB\n";
echo "  Products: " . number_format(count($products)) . "\n";
echo "  Valid XML: " . ($isValidXml ? 'Yes ✓' : 'No ✗') . "\n\n";

echo "Feed Sync Scenarios:\n";
echo "--------------------\n";
echo "  Hourly sync (5K products): " . number_format($avgTime, 1) . " ms ✓\n";
echo "  Daily full export: " . number_format($avgTime, 1) . " ms ✓\n";
echo "  Real-time updates: " . number_format($avgTime, 1) . " ms ✓\n\n";

echo "Sample Output (first 40 lines):\n";
echo "--------------------------------\n";
$lines = explode("\n", $result);
for ($i = 0; $i < min(40, count($lines)); $i++) {
    echo $lines[$i] . "\n";
}
if (count($lines) > 40) {
    echo "... (" . (count($lines) - 40) . " more lines)\n";
}
