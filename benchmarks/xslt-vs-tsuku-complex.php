<?php

/**
 * XSLT vs Tsuku: Complex Nested Structure Benchmark
 * Compares performance with nested loops and conditionals
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "XSLT vs Tsuku: Complex Nested Structure\n";
echo "========================================\n\n";

// Prepare data: 5 categories × 50 products = 250 items
$categories = array_fill(0, 5, [
    'name' => 'Electronics',
    'products' => array_fill(0, 50, [
        'name' => 'Premium Widget Pro',
        'price' => 1299.99,
        'stock' => 50,
        'status' => 'active',
    ])
]);

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = 'Product Catalog
@for(categories as cat)
Category: @upper(cat.name)
@for(cat.products as product)
  @if(product.stock > 0)
  - {product.name}: $@number(product.price, 2) (@match(product.status)
@case("active")
Available
@case("pending")
Pending
@default
Unknown
@end)
  @else
  - {product.name}: OUT OF STOCK
  @end
@end
@end';

$tsukuData = ['categories' => $categories];
$iterations = 100;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $tsukuData);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK ===
// Create XML from data
$xml = new DOMDocument('1.0', 'UTF-8');
$root = $xml->createElement('catalog');
$xml->appendChild($root);

foreach ($categories as $category) {
    $catNode = $xml->createElement('category');
    $catNode->appendChild($xml->createElement('name', $category['name']));

    foreach ($category['products'] as $product) {
        $productNode = $xml->createElement('product');
        $productNode->appendChild($xml->createElement('name', $product['name']));
        $productNode->appendChild($xml->createElement('price', (string)$product['price']));
        $productNode->appendChild($xml->createElement('stock', (string)$product['stock']));
        $productNode->appendChild($xml->createElement('status', $product['status']));
        $catNode->appendChild($productNode);
    }

    $root->appendChild($catNode);
}

// Create XSLT stylesheet
$xslt = new DOMDocument();
$xslt->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="text" encoding="UTF-8"/>
    <xsl:template match="/">
        <xsl:text>Product Catalog&#10;</xsl:text>
        <xsl:for-each select="catalog/category">
            <xsl:text>Category: </xsl:text>
            <xsl:value-of select="translate(name, \'abcdefghijklmnopqrstuvwxyz\', \'ABCDEFGHIJKLMNOPQRSTUVWXYZ\')"/>
            <xsl:text>&#10;</xsl:text>
            <xsl:for-each select="product">
                <xsl:choose>
                    <xsl:when test="stock &gt; 0">
                        <xsl:text>  - </xsl:text>
                        <xsl:value-of select="name"/>
                        <xsl:text>: $</xsl:text>
                        <xsl:value-of select="format-number(price, \'#,##0.00\')"/>
                        <xsl:text> (</xsl:text>
                        <xsl:choose>
                            <xsl:when test="status = \'active\'">Available</xsl:when>
                            <xsl:when test="status = \'pending\'">Pending</xsl:when>
                            <xsl:otherwise>Unknown</xsl:otherwise>
                        </xsl:choose>
                        <xsl:text>)&#10;</xsl:text>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:text>  - </xsl:text>
                        <xsl:value-of select="name"/>
                        <xsl:text>: OUT OF STOCK&#10;</xsl:text>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:for-each>
        </xsl:for-each>
    </xsl:template>
</xsl:stylesheet>');

$processor = new XSLTProcessor();
$processor->importStylesheet($xslt);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $processor->transformToXML($xml);
}
$xsltTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "Scenario: Nested categories and products with conditionals\n";
echo "Data: 5 categories × 50 products = 250 items\n";
echo "Iterations: " . number_format($iterations) . "\n\n";

echo "Tsuku:\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 4) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n\n";

echo "XSLT:\n";
echo "  Total time: " . number_format($xsltTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($xsltTime / $iterations, 4) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xsltTime / 1000)) . " renders/sec\n\n";

// Calculate winner
$speedup = $xsltTime / $tsukuTime;
if ($speedup > 1) {
    echo "Result: Tsuku is " . number_format($speedup, 2) . "x FASTER than XSLT\n";
} else {
    echo "Result: XSLT is " . number_format(1 / $speedup, 2) . "x faster than Tsuku\n";
}
