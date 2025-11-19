<?php

/**
 * Spatie Array-to-XML vs Tsuku: XML Feed Generation
 * Comparison with specialized XML library
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;
use Spatie\ArrayToXml\ArrayToXml;

echo "Spatie Array-to-XML vs Tsuku: XML Feed Generation\n";
echo "==================================================\n\n";

// Generate product data (Google Shopping format)
$products = [];
for ($i = 1; $i <= 5000; $i++) {
    $products[] = [
        'id' => $i,
        'title' => 'Product ' . $i,
        'description' => 'High-quality product with excellent features - Product #' . $i,
        'link' => 'https://example.com/product/' . $i,
        'image_link' => 'https://example.com/images/product-' . $i . '.jpg',
        'price' => rand(999, 99999) / 100,
        'availability' => ($i % 3 == 0) ? 'out of stock' : 'in stock',
        'brand' => 'Brand ' . (($i % 20) + 1),
        'condition' => 'new',
        'google_product_category' => 'Category ' . (($i % 10) + 1),
        'shipping' => [
            'country' => 'US',
            'service' => 'Standard',
            'price' => '4.99',
        ],
    ];
}

$data = ['products' => $products];
$iterations = 10;

echo "Dataset: 5,000 products (Google Shopping format)\n";
echo "Iterations: {$iterations}\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title>Product Feed</title>
    <link>https://example.com</link>
    <description>Google Shopping Product Feed</description>
@for(products as product)
    <item>
      <g:id>{product.id}</g:id>
      <g:title>@xml(product.title)</g:title>
      <g:description>@xml(product.description)</g:description>
      <g:link>@xml(product.link)</g:link>
      <g:image_link>@xml(product.image_link)</g:image_link>
      <g:price>@number(product.price, 2) USD</g:price>
      <g:availability>@xml(product.availability)</g:availability>
      <g:brand>@xml(product.brand)</g:brand>
      <g:condition>@xml(product.condition)</g:condition>
      <g:google_product_category>@xml(product.google_product_category)</g:google_product_category>
      <g:shipping>
        <g:country>@xml(product.shipping.country)</g:country>
        <g:service>@xml(product.shipping.service)</g:service>
        <g:price>@xml(product.shipping.price) USD</g:price>
      </g:shipping>
    </item>
@end
  </channel>
</rss>';

echo "Tsuku: Template-based XML generation...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === SPATIE ARRAY-TO-XML BENCHMARK ===
echo "Spatie Array-to-XML: Specialized XML library...\n";

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $items = [];
    foreach ($products as $product) {
        $items[] = [
            'g:id' => $product['id'],
            'g:title' => $product['title'],
            'g:description' => $product['description'],
            'g:link' => $product['link'],
            'g:image_link' => $product['image_link'],
            'g:price' => number_format($product['price'], 2, '.', '') . ' USD',
            'g:availability' => $product['availability'],
            'g:brand' => $product['brand'],
            'g:condition' => $product['condition'],
            'g:google_product_category' => $product['google_product_category'],
            'g:shipping' => [
                'g:country' => $product['shipping']['country'],
                'g:service' => $product['shipping']['service'],
                'g:price' => $product['shipping']['price'] . ' USD',
            ],
        ];
    }

    $arrayData = [
        '_attributes' => [
            'version' => '2.0',
            'xmlns:g' => 'http://base.google.com/ns/1.0',
        ],
        'channel' => [
            'title' => 'Product Feed',
            'link' => 'https://example.com',
            'description' => 'Google Shopping Product Feed',
            'item' => $items,
        ],
    ];

    $result = ArrayToXml::convert($arrayData, [
        'rootElementName' => 'rss',
        '_attributes' => [
            'version' => '2.0',
            'xmlns:g' => 'http://base.google.com/ns/1.0',
        ],
    ], true, 'UTF-8');
}
$spatieTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\nResults:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per feed: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000), 2) . " feeds/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "Spatie Array-to-XML:\n";
echo "  Total time: " . number_format($spatieTime, 2) . " ms\n";
echo "  Per feed: " . number_format($spatieTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($spatieTime / 1000), 2) . " feeds/sec\n";
echo "  Products/sec: " . number_format((count($products) * $iterations) / ($spatieTime / 1000)) . "\n\n";

// Calculate winner
echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

if ($tsukuTime < $spatieTime) {
    $speedup = $spatieTime / $tsukuTime;
    echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than Spatie Array-to-XML!\n";
} else {
    $speedup = $tsukuTime / $spatieTime;
    echo "⚡ Spatie Array-to-XML is " . number_format($speedup, 2) . "x FASTER than Tsuku!\n";
}

echo "\nTime difference: " . number_format(abs($tsukuTime - $spatieTime), 2) . " ms\n\n";

echo "Code Comparison:\n";
echo "----------------\n\n";

echo "Tsuku (template-based - works for CSV, JSON, XML):\n";
echo "  \$template = '<?xml version=\"1.0\"?>\\n";
echo "  <rss>\\n";
echo "    @for(products as p)\\n";
echo "    <item>\\n";
echo "      <g:id>{p.id}</g:id>\\n";
echo "      <g:title>@xml(p.title)</g:title>\\n";
echo "      ...\\n";
echo "    </item>\\n";
echo "    @end\\n";
echo "  </rss>';\\n";
echo "  \$output = \$tsuku->process(\$template, \$data);\\n\\n";

echo "Spatie Array-to-XML (~25 lines - XML only):\n";
echo "  \$items = [];\\n";
echo "  foreach (\$products as \$p) {\\n";
echo "    \$items[] = [\\n";
echo "      'g:id' => \$p['id'],\\n";
echo "      'g:title' => \$p['title'],\\n";
echo "      'g:shipping' => [\\n";
echo "        'g:country' => \$p['shipping']['country'],\\n";
echo "        ...\\n";
echo "      ],\\n";
echo "    ];\\n";
echo "  }\\n";
echo "  \$result = ArrayToXml::convert(\$arrayData, [...]);\\n\\n";

echo "Key Differences:\n";
echo "----------------\n";
echo "Spatie Array-to-XML:\n";
echo "  ✓ Specialized for XML (very good at one thing)\n";
echo "  ✓ Array-based approach (programmatic)\n";
echo "  ✓ Battle-tested library\n";
echo "  ✗ Only does XML (need different library for CSV/JSON)\n";
echo "  ✗ More verbose (need to build array structure)\n";
echo "  ✗ Less readable for complex structures\n\n";

echo "Tsuku:\n";
echo "  ✓ Works for CSV, JSON, XML, TSV with same syntax\n";
echo "  ✓ Template-based (readable, maintainable)\n";
echo "  ✓ Less code for same result\n";
echo "  ✓ Direct control over XML structure\n";
echo "  ✗ Less specialized XML features\n\n";

echo "Verdict:\n";
echo "--------\n";
echo "• Choose Spatie Array-to-XML if: You only need XML and prefer array-based approach\n";
echo "• Choose Tsuku if: You need multiple formats with template-based approach\n";
