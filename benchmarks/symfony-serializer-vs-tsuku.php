<?php

/**
 * Symfony Serializer vs Tsuku: JSON API Response
 * Comparison with Symfony's JSON serialization
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

echo "Symfony Serializer vs Tsuku: JSON API Response\n";
echo "===============================================\n\n";

// Generate product data with nested objects
$products = [];
for ($i = 1; $i <= 1000; $i++) {
    $products[] = [
        'id' => $i,
        'sku' => 'SKU-' . str_pad((string)$i, 6, '0', STR_PAD_LEFT),
        'name' => 'Product ' . $i,
        'price' => rand(999, 99999) / 100,
        'inStock' => ($i % 3 != 0),
        'category' => [
            'id' => ($i % 10) + 1,
            'name' => 'Category ' . (($i % 10) + 1),
            'slug' => 'category-' . (($i % 10) + 1),
        ],
    ];
}

$data = [
    'products' => $products,
    'page' => 1,
    'total' => 1000,
];

$iterations = 100;

echo "Dataset: 1,000 products with nested category objects\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = '{
  "data": [
@for(products as product, index)@if(index > 0),@end
    {
      "id": {product.id},
      "type": "product",
      "attributes": {
        "sku": "@escape(product.sku, "json")",
        "name": "@escape(product.name, "json")",
        "price": @number(product.price, 2),
        "inStock": @if(product.inStock)true@elsefalse@end
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
  "meta": {
    "page": {page},
    "total": {total}
  }
}';

echo "Tsuku: Template-based JSON generation...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === SYMFONY SERIALIZER BENCHMARK ===
echo "Symfony Serializer: JSON encoding (using JsonEncoder)...\n";

$encoders = [new JsonEncoder()];
$serializer = new Serializer([], $encoders);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    // Transform data to match JSON-API format
    $apiData = [
        'data' => [],
        'meta' => [
            'page' => $data['page'],
            'total' => $data['total'],
        ],
    ];

    foreach ($products as $product) {
        $apiData['data'][] = [
            'id' => $product['id'],
            'type' => 'product',
            'attributes' => [
                'sku' => $product['sku'],
                'name' => $product['name'],
                'price' => round($product['price'], 2),
                'inStock' => $product['inStock'],
            ],
            'relationships' => [
                'category' => [
                    'data' => [
                        'id' => $product['category']['id'],
                        'type' => 'category',
                    ],
                ],
            ],
        ];
    }

    $result = $serializer->serialize($apiData, 'json');
}
$symfonyTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per response: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000), 2) . " responses/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Symfony Serializer:\n";
echo "  Total time: " . number_format($symfonyTime, 2) . " ms\n";
echo "  Per response: " . number_format($symfonyTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($symfonyTime / 1000), 2) . " responses/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($symfonyTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $symfonyTime) {
    $speedup = $symfonyTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than Symfony Serializer!\n";
} else {
    $speedup = $tsukuTime / $symfonyTime;
    echo "⚡ Symfony Serializer is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $symfonyTime), 2) . " ms\n\n";

echo "Code Comparison:\n";
echo "----------------\n\n";

echo "Tsuku (template-based - works for CSV, JSON, XML):\n";
echo "  \$template = '{\\n";
echo "    \"data\": [\\n";
echo "    @for(products as p, i)@if(i > 0),@end\\n";
echo "      {\\n";
echo "        \"id\": {p.id},\\n";
echo "        \"attributes\": {\\n";
echo "          \"sku\": \"@escape(p.sku, \"json\")\",\\n";
echo "          \"price\": @number(p.price, 2)\\n";
echo "        }\\n";
echo "      }@end\\n";
echo "    ]\\n";
echo "  }';\\n";
echo "  \$output = \$tsuku->process(\$template, \$data);\\n\\n";

echo "Symfony Serializer (~25 lines - needs data transformation):\n";
echo "  \$serializer = new Serializer(\$normalizers, \$encoders);\\n";
echo "  \$apiData = ['data' => []];\\n";
echo "  foreach (\$products as \$p) {\\n";
echo "    \$apiData['data'][] = [\\n";
echo "      'id' => \$p['id'],\\n";
echo "      'attributes' => [\\n";
echo "        'sku' => \$p['sku'],\\n";
echo "        'price' => round(\$p['price'], 2),\\n";
echo "        ...\\n";
echo "      ],\\n";
echo "      'relationships' => [...],\\n";
echo "    ];\\n";
echo "  }\\n";
echo "  \$json = \$serializer->serialize(\$apiData, 'json');\\n\\n";

echo "Key Differences:\n";
echo "----------------\n";
echo "Symfony Serializer:\n";
echo "  ✓ Part of Symfony ecosystem\n";
echo "  ✓ Rich features (normalization, denormalization, formats)\n";
echo "  ✓ Object serialization support\n";
echo "  ✓ Battle-tested in production\n";
echo "  ✗ Requires data transformation for custom structures\n";
echo "  ✗ More setup (normalizers, encoders)\n";
echo "  ✗ Primarily JSON/XML (not CSV, TSV)\n\n";

echo "Tsuku:\n";
echo "  ✓ Works for CSV, JSON, XML, TSV with same syntax\n";
echo "  ✓ Template-based (readable, maintainable)\n";
echo "  ✓ Direct control over output structure\n";
echo "  ✓ No data transformation needed\n";
echo "  ✗ Less enterprise features (no denormalization)\n";
echo "  ✗ Not part of major framework\n\n";

echo "Verdict:\n";
echo "--------\n";
echo "• Choose Symfony Serializer if: You're in Symfony ecosystem and need object serialization\n";
echo "• Choose Tsuku if: You need flexible template-based transformation for multiple formats\n";
