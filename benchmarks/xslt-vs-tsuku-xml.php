<?php

/**
 * XSLT vs Tsuku: XML Output Generation Benchmark
 * Compares performance for XML generation (XSLT's native format)
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "XSLT vs Tsuku: XML Output Generation\n";
echo "=====================================\n\n";

// Prepare data: 100 products
$products = array_fill(0, 100, [
    'id' => '12345',
    'sku' => 'WID-001',
    'name' => 'Premium Widget & Co.',
    'price' => 1299.99,
    'stock' => 50,
    'status' => 'active',
]);

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = '<?xml version="1.0" encoding="UTF-8"?>
<catalog>
@for(products as product)
  <product id="{product.id}">
    <sku>@xml(product.sku)</sku>
    <name>@xml(product.name)</name>
    <price>{product.price}</price>
    <stock>{product.stock}</stock>
    @if(product.stock > 0)
    <availability>in-stock</availability>
    @else
    <availability>out-of-stock</availability>
    @end
    <status>{product.status}</status>
  </product>
@end
</catalog>';

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
    $productNode->setAttribute('id', $product['id']);
    $productNode->appendChild($xml->createElement('sku', $product['sku']));
    $productNode->appendChild($xml->createElement('name', htmlspecialchars($product['name'], ENT_XML1)));
    $productNode->appendChild($xml->createElement('price', (string)$product['price']));
    $productNode->appendChild($xml->createElement('stock', (string)$product['stock']));
    $productNode->appendChild($xml->createElement('status', $product['status']));
    $root->appendChild($productNode);
}

// Create XSLT stylesheet for XML-to-XML transformation
$xslt = new DOMDocument();
$xslt->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="xml" encoding="UTF-8" indent="no"/>
    <xsl:template match="/">
        <catalog>
            <xsl:for-each select="products/product">
                <product>
                    <xsl:attribute name="id"><xsl:value-of select="@id"/></xsl:attribute>
                    <sku><xsl:value-of select="sku"/></sku>
                    <name><xsl:value-of select="name"/></name>
                    <price><xsl:value-of select="price"/></price>
                    <stock><xsl:value-of select="stock"/></stock>
                    <xsl:choose>
                        <xsl:when test="stock &gt; 0">
                            <availability>in-stock</availability>
                        </xsl:when>
                        <xsl:otherwise>
                            <availability>out-of-stock</availability>
                        </xsl:otherwise>
                    </xsl:choose>
                    <status><xsl:value-of select="status"/></status>
                </product>
            </xsl:for-each>
        </catalog>
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
echo "Scenario: XML product catalog generation\n";
echo "Products: 100\n";
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

echo "\nNote: XSLT requires XML input, while Tsuku works directly with PHP arrays.\n";
echo "      XML creation overhead is NOT included in XSLT timing (only transformation).\n";
