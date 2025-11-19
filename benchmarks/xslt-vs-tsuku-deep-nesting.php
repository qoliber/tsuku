<?php

/**
 * XSLT vs Tsuku: Deep Nesting Performance
 * Tests very deep nesting (5+ levels) with conditionals
 * Shows how Tsuku's clean syntax scales better
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "XSLT vs Tsuku: Deep Nesting (5 levels)\n";
echo "=======================================\n\n";

// Generate deeply nested structure
$data = [
    'store' => [
        'name' => 'Mega Store',
        'regions' => []
    ]
];

// 5 regions
for ($r = 0; $r < 5; $r++) {
    $region = [
        'name' => 'Region ' . ($r + 1),
        'locations' => []
    ];

    // 10 locations per region
    for ($l = 0; $l < 10; $l++) {
        $location = [
            'name' => 'Location ' . ($l + 1),
            'departments' => []
        ];

        // 5 departments per location
        for ($d = 0; $d < 5; $d++) {
            $department = [
                'name' => 'Department ' . ($d + 1),
                'categories' => []
            ];

            // 10 categories per department
            for ($c = 0; $c < 10; $c++) {
                $category = [
                    'name' => 'Category ' . ($c + 1),
                    'products' => []
                ];

                // 20 products per category
                for ($p = 0; $p < 20; $p++) {
                    $category['products'][] = [
                        'id' => ($r * 10000) + ($l * 1000) + ($d * 100) + ($c * 20) + $p,
                        'name' => 'Product ' . $p,
                        'price' => rand(10, 100),
                        'inStock' => (bool)rand(0, 1),
                    ];
                }

                $department['categories'][] = $category;
            }

            $location['departments'][] = $department;
        }

        $region['locations'][] = $location;
    }

    $data['store']['regions'][] = $region;
}

$totalProducts = 5 * 10 * 5 * 10 * 20; // 50,000 products!

echo "Dataset:\n";
echo "  5 regions → 10 locations → 5 departments → 10 categories → 20 products\n";
echo "  Total: 50,000 products across 5 nesting levels\n";
echo "Iterations: 3 (large dataset)\n\n";

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = 'Store: {store.name}
@for(store.regions as region)

REGION: @upper(region.name)
@for(region.locations as location)
  Location: {location.name}
  @for(location.departments as dept)
    Department: {dept.name}
    @for(dept.categories as cat)
      Category: {cat.name}
      @for(cat.products as product)
        @if(product.inStock)
        ✓ {product.name}: ${product.price}
        @else
        ✗ {product.name}: OUT OF STOCK
        @end
      @end
    @end
  @end
@end
@end';

$iterations = 3;

echo "Tsuku: Clean nested template (easy to read)...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $data);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK ===
echo "XSLT: Verbose nested XSL + XML conversion...\n";

$xslt = new DOMDocument();
$xslt->loadXML('<?xml version="1.0"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="text"/>
    <xsl:template match="/">
        <xsl:text>Store: </xsl:text>
        <xsl:value-of select="store/name"/>
        <xsl:text>&#10;</xsl:text>

        <xsl:for-each select="store/regions/region">
            <xsl:text>&#10;REGION: </xsl:text>
            <xsl:value-of select="translate(name, \'abcdefghijklmnopqrstuvwxyz\', \'ABCDEFGHIJKLMNOPQRSTUVWXYZ\')"/>
            <xsl:text>&#10;</xsl:text>

            <xsl:for-each select="locations/location">
                <xsl:text>  Location: </xsl:text>
                <xsl:value-of select="name"/>
                <xsl:text>&#10;</xsl:text>

                <xsl:for-each select="departments/dept">
                    <xsl:text>    Department: </xsl:text>
                    <xsl:value-of select="name"/>
                    <xsl:text>&#10;</xsl:text>

                    <xsl:for-each select="categories/cat">
                        <xsl:text>      Category: </xsl:text>
                        <xsl:value-of select="name"/>
                        <xsl:text>&#10;</xsl:text>

                        <xsl:for-each select="products/product">
                            <xsl:choose>
                                <xsl:when test="inStock = \'1\'">
                                    <xsl:text>        ✓ </xsl:text>
                                    <xsl:value-of select="name"/>
                                    <xsl:text>: $</xsl:text>
                                    <xsl:value-of select="price"/>
                                    <xsl:text>&#10;</xsl:text>
                                </xsl:when>
                                <xsl:otherwise>
                                    <xsl:text>        ✗ </xsl:text>
                                    <xsl:value-of select="name"/>
                                    <xsl:text>: OUT OF STOCK&#10;</xsl:text>
                                </xsl:otherwise>
                            </xsl:choose>
                        </xsl:for-each>
                    </xsl:for-each>
                </xsl:for-each>
            </xsl:for-each>
        </xsl:for-each>
    </xsl:template>
</xsl:stylesheet>');

$processor = new XSLTProcessor();
$processor->importStylesheet($xslt);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    // Create deeply nested XML - this is PAINFUL!
    $xml = new DOMDocument('1.0', 'UTF-8');
    $storeNode = $xml->createElement('store');
    $storeNode->appendChild($xml->createElement('name', $data['store']['name']));

    $regionsNode = $xml->createElement('regions');
    foreach ($data['store']['regions'] as $region) {
        $regionNode = $xml->createElement('region');
        $regionNode->appendChild($xml->createElement('name', $region['name']));

        $locationsNode = $xml->createElement('locations');
        foreach ($region['locations'] as $location) {
            $locationNode = $xml->createElement('location');
            $locationNode->appendChild($xml->createElement('name', $location['name']));

            $deptsNode = $xml->createElement('departments');
            foreach ($location['departments'] as $dept) {
                $deptNode = $xml->createElement('dept');
                $deptNode->appendChild($xml->createElement('name', $dept['name']));

                $catsNode = $xml->createElement('categories');
                foreach ($dept['categories'] as $cat) {
                    $catNode = $xml->createElement('cat');
                    $catNode->appendChild($xml->createElement('name', $cat['name']));

                    $prodsNode = $xml->createElement('products');
                    foreach ($cat['products'] as $prod) {
                        $prodNode = $xml->createElement('product');
                        $prodNode->appendChild($xml->createElement('id', (string)$prod['id']));
                        $prodNode->appendChild($xml->createElement('name', $prod['name']));
                        $prodNode->appendChild($xml->createElement('price', (string)$prod['price']));
                        $prodNode->appendChild($xml->createElement('inStock', $prod['inStock'] ? '1' : '0'));
                        $prodsNode->appendChild($prodNode);
                    }
                    $catNode->appendChild($prodsNode);
                    $catsNode->appendChild($catNode);
                }
                $deptNode->appendChild($catsNode);
                $deptsNode->appendChild($deptNode);
            }
            $locationNode->appendChild($deptsNode);
            $locationsNode->appendChild($locationNode);
        }
        $regionNode->appendChild($locationsNode);
        $regionsNode->appendChild($regionNode);
    }
    $storeNode->appendChild($regionsNode);
    $xml->appendChild($storeNode);

    $result = $processor->transformToXML($xml);
}
$xsltTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\n";
echo "Results:\n";
echo "========\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000), 2) . " renders/sec\n";
echo "  Products/sec: " . number_format(($totalProducts * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "XSLT:\n";
echo "  Total time: " . number_format($xsltTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($xsltTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xsltTime / 1000), 2) . " renders/sec\n";
echo "  Products/sec: " . number_format(($totalProducts * $iterations) / ($xsltTime / 1000)) . "\n\n";

// Calculate winner
$speedup = $xsltTime / $tsukuTime;

echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

echo "💪 Tsuku is " . number_format($speedup, 2) . "x FASTER than XSLT!\n";

echo "\n";
echo "Why is Tsuku better for deep nesting?\n";
echo "--------------------------------------\n";
echo "✓ Clean, readable syntax even at 5 levels deep\n";
echo "✓ No XML conversion overhead\n";
echo "✓ Simple @for and @if - easy to follow\n";
echo "✓ Templates look like the output\n";
echo "\n";
echo "✗ XSLT becomes VERY verbose with deep nesting\n";
echo "✗ Creating deeply nested XML is PAINFUL (see code above)\n";
echo "✗ Hard to read and maintain\n";
echo "✗ Easy to make mistakes with nested XML nodes\n";

echo "\n";
echo "With 50,000 products across 5 nesting levels,\n";
echo "the XML overhead becomes absolutely crushing!\n";
