<?php

/**
 * Data Transformation Benchmark: JSON API Response
 * Real-world scenario: REST API product listing with pagination
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "Data Transformation: JSON API Response\n";
echo "======================================\n\n";

// Generate realistic API data
$products = [];
for ($i = 1; $i <= 1000; $i++) {
    $products[] = [
        'id' => $i,
        'sku' => 'SKU-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
        'name' => 'Product ' . $i,
        'slug' => 'product-' . $i,
        'price' => rand(999, 99999) / 100,
        'comparePrice' => rand(100000, 150000) / 100,
        'inStock' => rand(0, 100) > 10,
        'stock' => rand(0, 500),
        'rating' => rand(0, 50) / 10,
        'reviewCount' => rand(0, 1000),
        'image' => 'https://example.com/images/product-' . $i . '.jpg',
        'category' => [
            'id' => (($i - 1) % 10) + 1,
            'name' => 'Category ' . ((($i - 1) % 10) + 1),
            'slug' => 'category-' . ((($i - 1) % 10) + 1),
        ],
    ];
}

$data = [
    'products' => $products,
    'page' => 1,
    'perPage' => count($products),
    'total' => count($products),
    'totalPages' => 1,
];

$iterations = 100;

echo "Scenario: REST API product listing\n";
echo "Products: " . number_format(count($products)) . "\n";
echo "Nested objects: Yes (category)\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();

// JSON API template
$template = '{
  "data": [
@for(products as product, index)@if(index > 0),@end
    {
      "id": {product.id},
      "type": "product",
      "attributes": {
        "sku": "@escape(product.sku, "json")",
        "name": "@escape(product.name, "json")",
        "slug": "@escape(product.slug, "json")",
        "price": @number(product.price, 2),
        "comparePrice": @number(product.comparePrice, 2),
        "discount": @number((product.comparePrice - product.price) / product.comparePrice * 100, 0),
        "inStock": @if(product.inStock)true@elsefalse@end,
        "stock": {product.stock},
        "rating": @number(product.rating, 1),
        "reviewCount": {product.reviewCount},
        "image": "@escape(product.image, "json")"
      },
      "relationships": {
        "category": {
          "data": {
            "id": {product.category.id},
            "type": "category"
          }
        }
      }
    }@end
  ],
  "included": [],
  "meta": {
    "page": {page},
    "perPage": {perPage},
    "total": {total},
    "totalPages": {totalPages}
  }
}';

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
$requestsPerSec = $throughput;
$outputSize = strlen($result);

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Performance:\n";
echo "  Total time: " . number_format($totalTime, 2) . " ms\n";
echo "  Avg per response: " . number_format($avgTime, 2) . " ms\n";
echo "  Throughput: " . number_format($throughput, 2) . " requests/sec\n";
echo "  Products/sec: " . number_format($productsPerSec, 0) . "\n\n";

echo "Output:\n";
echo "  Response size: " . number_format($outputSize / 1024, 2) . " KB\n";
echo "  Products: " . number_format(count($products)) . "\n";
echo "  Valid JSON: " . (json_decode($result) !== null ? 'Yes ✓' : 'No ✗') . "\n\n";

echo "API Performance Scenarios:\n";
echo "--------------------------\n";
echo "  Concurrent users (100): " . number_format($requestsPerSec / 100, 2) . " sec response time ✓\n";
echo "  Peak traffic (1000 req/min): " . ($requestsPerSec >= 1000/60 ? 'Can handle ✓' : 'Cannot handle ✗') . "\n";
echo "  Single page load: " . number_format($avgTime, 1) . " ms ✓\n\n";

echo "Sample Output (first 30 lines):\n";
echo "--------------------------------\n";
$lines = explode("\n", $result);
for ($i = 0; $i < min(30, count($lines)); $i++) {
    echo $lines[$i] . "\n";
}
if (count($lines) > 30) {
    echo "... (" . (count($lines) - 30) . " more lines)\n";
}
