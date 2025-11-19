<?php

/**
 * XSLT vs Tsuku: Simple CSV Export Benchmark
 * Compares performance of simple CSV generation
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "XSLT vs Tsuku: Simple CSV Export\n";
echo "=================================\n\n";

// Prepare data
$products = array_fill(0, 100, [
    'sku' => 'WID-001',
    'name' => 'Premium Widget',
    'price' => 1299.99,
    'stock' => 50,
]);

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = 'SKU,Name,Price,Stock
@for(products as product)
{product.sku},{product.name},$@number(product.price, 2),{product.stock}
@end';

$tsukuData = ['products' => $products];
$iterations = 1000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $tsukuData);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK ===
// Create XML from data
$xml = new DOMDocument('1.0', 'UTF-8');
$root = $xml->createElement('products');
$xml->appendChild($root);

foreach ($products as $product) {
    $productNode = $xml->createElement('product');
    $productNode->appendChild($xml->createElement('sku', $product['sku']));
    $productNode->appendChild($xml->createElement('name', $product['name']));
    $productNode->appendChild($xml->createElement('price', (string)$product['price']));
    $productNode->appendChild($xml->createElement('stock', (string)$product['stock']));
    $root->appendChild($productNode);
}

// Create XSLT stylesheet
$xslt = new DOMDocument();
$xslt->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="text" encoding="UTF-8"/>
    <xsl:template match="/">
        <xsl:text>SKU,Name,Price,Stock&#10;</xsl:text>
        <xsl:for-each select="products/product">
            <xsl:value-of select="sku"/>
            <xsl:text>,</xsl:text>
            <xsl:value-of select="name"/>
            <xsl:text>,$</xsl:text>
            <xsl:value-of select="format-number(price, \'#,##0.00\')"/>
            <xsl:text>,</xsl:text>
            <xsl:value-of select="stock"/>
            <xsl:text>&#10;</xsl:text>
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
echo "Scenario: CSV export with 100 products\n";
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
