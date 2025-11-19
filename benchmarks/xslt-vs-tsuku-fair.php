<?php

/**
 * XSLT vs Tsuku: Fair Comparison (includes XML creation overhead)
 * This benchmark includes the cost of creating XML for XSLT,
 * making it a fair comparison with Tsuku which works directly with arrays
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "XSLT vs Tsuku: Fair Comparison (with XML creation overhead)\n";
echo "============================================================\n\n";

// Prepare data: 100 products
$products = array_fill(0, 100, [
    'sku' => 'WID-001',
    'name' => 'Premium Widget',
    'price' => 1299.99,
    'stock' => 50,
]);

// === TSUKU BENCHMARK ===
// Tsuku works directly with PHP arrays
$tsuku = new Tsuku();
$tsukuTemplate = 'SKU,Name,Price,Stock
@for(products as product)
{product.sku},{product.name},$@number(product.price, 2),{product.stock}
@end';

$iterations = 1000;

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $tsukuData = ['products' => $products]; // Simulating data preparation
    $result = $tsuku->process($tsukuTemplate, $tsukuData);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK (with XML creation) ===
// XSLT requires XML, so we include the conversion time
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
    // Create XML from array (this is the overhead XSLT has)
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

    // Now transform
    $result = $processor->transformToXML($xml);
}
$xsltTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK (transformation only, for reference) ===
// Create XML once
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

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $processor->transformToXML($xml);
}
$xsltOnlyTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "Scenario: CSV export with 100 products\n";
echo "Iterations: " . number_format($iterations) . "\n\n";

echo "Tsuku (works directly with arrays):\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 4) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n\n";

echo "XSLT (transformation only, pre-created XML):\n";
echo "  Total time: " . number_format($xsltOnlyTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($xsltOnlyTime / $iterations, 4) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xsltOnlyTime / 1000)) . " renders/sec\n\n";

echo "XSLT (FAIR: includes XML creation from arrays):\n";
echo "  Total time: " . number_format($xsltTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($xsltTime / $iterations, 4) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xsltTime / 1000)) . " renders/sec\n";
echo "  XML creation overhead: " . number_format($xsltTime - $xsltOnlyTime, 2) . " ms (" .
    number_format((($xsltTime - $xsltOnlyTime) / $xsltTime) * 100, 1) . "%)\n\n";

// Calculate winner
$speedupVsTransformOnly = $xsltOnlyTime / $tsukuTime;
$speedupVsFair = $xsltTime / $tsukuTime;

echo str_repeat('=', 60) . "\n";
echo "RESULTS:\n";
echo str_repeat('=', 60) . "\n\n";

if ($speedupVsTransformOnly > 1) {
    echo "vs XSLT (transform only): XSLT is " . number_format($speedupVsTransformOnly, 2) . "x faster\n";
} else {
    echo "vs XSLT (transform only): Tsuku is " . number_format(1 / $speedupVsTransformOnly, 2) . "x FASTER\n";
}

if ($speedupVsFair > 1) {
    echo "vs XSLT (fair/full):      XSLT is " . number_format($speedupVsFair, 2) . "x faster\n";
} else {
    echo "vs XSLT (fair/full):      Tsuku is " . number_format(1 / $speedupVsFair, 2) . "x FASTER\n";
}

echo "\n";
echo "CONCLUSION:\n";
echo "-----------\n";
if ($speedupVsFair < 1) {
    echo "When including XML creation overhead (fair comparison),\n";
    echo "Tsuku is significantly FASTER than XSLT!\n";
    echo "\n";
    echo "Tsuku works directly with PHP arrays/objects, avoiding the\n";
    echo "expensive XML DOM creation step that XSLT requires.\n";
} else {
    echo "XSLT is faster even with XML creation overhead.\n";
}
