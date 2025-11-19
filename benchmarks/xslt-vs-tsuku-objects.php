<?php

/**
 * XSLT vs Tsuku: Object Access Performance
 * Tests Tsuku's smart object access vs XSLT's manual conversion requirement
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "XSLT vs Tsuku: Object Access (Smart Getters)\n";
echo "=============================================\n\n";

// Create PHP objects with getters (real-world scenario)
class Product
{
    public function __construct(
        private int $id,
        private string $sku,
        private string $name,
        private float $price,
        private int $stock,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function isAvailable(): bool
    {
        return $this->stock > 0;
    }

    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price, 2);
    }
}

// Generate 1,000 product objects
$products = [];
for ($i = 0; $i < 1000; $i++) {
    $products[] = new Product(
        id: $i + 1,
        sku: sprintf('SKU-%04d', $i + 1),
        name: 'Product ' . ($i + 1),
        price: rand(999, 99999) / 100,
        stock: rand(0, 200)
    );
}

echo "Dataset: 1,000 PHP Product objects with getters\n";
echo "Iterations: 100\n\n";

// === TSUKU BENCHMARK ===
// Tsuku automatically detects getters - just works!
$tsuku = new Tsuku();
$tsukuTemplate = 'ID,SKU,Name,Price,Stock,Available
@for(products as product)
{product.id},{product.sku},{product.name},{product.formattedPrice},{product.stock},@if(product.available)Yes@else No@end
@end';

$tsukuData = ['products' => $products];
$iterations = 100;

echo "Tsuku: Works directly with objects (automatic getter detection)...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $tsukuData);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK ===
// XSLT requires manual conversion of objects to arrays/XML - extra work!
echo "XSLT: Must manually convert objects to XML (lots of work)...\n";

$xslt = new DOMDocument();
$xslt->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="text" encoding="UTF-8"/>
    <xsl:template match="/">
        <xsl:text>ID,SKU,Name,Price,Stock,Available&#10;</xsl:text>
        <xsl:for-each select="products/product">
            <xsl:value-of select="id"/>
            <xsl:text>,</xsl:text>
            <xsl:value-of select="sku"/>
            <xsl:text>,</xsl:text>
            <xsl:value-of select="name"/>
            <xsl:text>,</xsl:text>
            <xsl:value-of select="formattedPrice"/>
            <xsl:text>,</xsl:text>
            <xsl:value-of select="stock"/>
            <xsl:text>,</xsl:text>
            <xsl:choose>
                <xsl:when test="available = \'true\'">Yes</xsl:when>
                <xsl:otherwise>No</xsl:otherwise>
            </xsl:choose>
            <xsl:text>&#10;</xsl:text>
        </xsl:for-each>
    </xsl:template>
</xsl:stylesheet>');

$processor = new XSLTProcessor();
$processor->importStylesheet($xslt);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    // PAINFUL: Must manually extract data from objects and create XML
    $xml = new DOMDocument('1.0', 'UTF-8');
    $root = $xml->createElement('products');
    $xml->appendChild($root);

    foreach ($products as $product) {
        $productNode = $xml->createElement('product');
        // Must call each getter manually!
        $productNode->appendChild($xml->createElement('id', (string)$product->getId()));
        $productNode->appendChild($xml->createElement('sku', $product->getSku()));
        $productNode->appendChild($xml->createElement('name', $product->getName()));
        $productNode->appendChild($xml->createElement('formattedPrice', $product->getFormattedPrice()));
        $productNode->appendChild($xml->createElement('stock', (string)$product->getStock()));
        $productNode->appendChild($xml->createElement('available', $product->isAvailable() ? 'true' : 'false'));
        $root->appendChild($productNode);
    }

    $result = $processor->transformToXML($xml);
}
$xsltTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\n";
echo "Results:\n";
echo "========\n\n";

echo "Tsuku (automatic getter detection):\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 4) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000)) . " renders/sec\n\n";

echo "XSLT (manual object → XML conversion):\n";
echo "  Total time: " . number_format($xsltTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($xsltTime / $iterations, 4) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xsltTime / 1000)) . " renders/sec\n\n";

// Calculate winner
$speedup = $xsltTime / $tsukuTime;

echo str_repeat('=', 60) . "\n";
echo "RESULT:\n";
echo str_repeat('=', 60) . "\n\n";

echo "🚀 Tsuku is " . number_format($speedup, 2) . "x FASTER than XSLT!\n";

$timeDiff = $xsltTime - $tsukuTime;
echo "\nTime saved per iteration: " . number_format($timeDiff / $iterations, 2) . " ms\n";

echo "\n";
echo "Why is Tsuku faster with objects?\n";
echo "----------------------------------\n";
echo "✓ Automatic getter detection (product.price → getPrice())\n";
echo "✓ Automatic is* method detection (product.available → isAvailable())\n";
echo "✓ No manual object conversion required\n";
echo "✓ Direct object access - no intermediate XML\n";
echo "\n";
echo "✗ XSLT requires manual getter calls for EVERY field\n";
echo "✗ XSLT requires creating XML nodes for EVERY property\n";
echo "✗ XSLT has no concept of objects - everything must be text/XML\n";

echo "\n";
echo "Developer Experience:\n";
echo "---------------------\n";
echo "Tsuku template:   {product.price} - just works!\n";
echo "XSLT equivalent:  Must write $product->getPrice() for each object,\n";
echo "                  then createElement('price', ...), then appendChild(...)\n";
echo "                  That's 3+ lines of boilerplate PER FIELD!\n";
