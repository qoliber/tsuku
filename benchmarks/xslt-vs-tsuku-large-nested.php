<?php

/**
 * XSLT vs Tsuku: Large Nested Dataset Comparison
 * 50 categories × 100 products = 5,000 products
 * Multiple nested loops and conditionals
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "XSLT vs Tsuku: Large Nested Dataset (5,000 products)\n";
echo "=====================================================\n\n";

// Generate realistic e-commerce data
$categories = [];
for ($c = 0; $c < 50; $c++) {
    $categoryName = 'Category-' . ($c + 1);
    $products = [];

    for ($p = 0; $p < 100; $p++) {
        $products[] = [
            'id' => ($c * 100) + $p + 1,
            'sku' => sprintf('SKU-%04d-%04d', $c + 1, $p + 1),
            'name' => 'Product ' . (($c * 100) + $p + 1),
            'price' => rand(999, 99999) / 100,
            'stock' => rand(0, 200),
            'onSale' => (bool)rand(0, 1),
            'discount' => rand(0, 50),
            'featured' => (bool)rand(0, 4),
        ];
    }

    $categories[] = [
        'id' => $c + 1,
        'name' => $categoryName,
        'active' => true,
        'products' => $products,
    ];
}

$totalProducts = 50 * 100;

// === TSUKU BENCHMARK ===
$tsuku = new Tsuku();
$tsukuTemplate = 'PRODUCT CATALOG
===============

@for(categories as cat)
@if(cat.active)
Category: @upper(cat.name) (ID: {cat.id})
@for(cat.products as product)
  @if(product.featured)⭐ @end{product.name}
  SKU: {product.sku}
  @if(product.onSale)
  SALE: $@number(product.price, 2) (@number(product.discount, 0)% OFF)
  @else
  Price: $@number(product.price, 2)
  @end
  @if(product.stock > 100)
  Stock: HIGH ({product.stock})
  @else
  @if(product.stock > 0)
  Stock: LOW ({product.stock})
  @else
  Stock: OUT OF STOCK
  @end
  @end
  ---
@end

@end
@end';

$tsukuData = ['categories' => $categories];
$iterations = 10;

echo "Dataset: 50 categories × 100 products = 5,000 products\n";
echo "Iterations: {$iterations}\n\n";

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $tsuku->process($tsukuTemplate, $tsukuData);
}
$tsukuTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK (with XML creation overhead) ===
echo "Creating XML structure from PHP arrays...\n";

$xmlCreationStart = microtime(true);

// Create XSLT stylesheet (only once)
$xslt = new DOMDocument();
$xslt->loadXML('<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="text" encoding="UTF-8"/>
    <xsl:template match="/">
        <xsl:text>PRODUCT CATALOG&#10;===============&#10;&#10;</xsl:text>
        <xsl:for-each select="catalog/category">
            <xsl:if test="active = \'true\'">
                <xsl:text>Category: </xsl:text>
                <xsl:value-of select="translate(name, \'abcdefghijklmnopqrstuvwxyz\', \'ABCDEFGHIJKLMNOPQRSTUVWXYZ\')"/>
                <xsl:text> (ID: </xsl:text>
                <xsl:value-of select="id"/>
                <xsl:text>)&#10;</xsl:text>

                <xsl:for-each select="products/product">
                    <xsl:text>  </xsl:text>
                    <xsl:if test="featured = \'true\'">
                        <xsl:text>⭐ </xsl:text>
                    </xsl:if>
                    <xsl:value-of select="name"/>
                    <xsl:text>&#10;  SKU: </xsl:text>
                    <xsl:value-of select="sku"/>
                    <xsl:text>&#10;  </xsl:text>

                    <xsl:choose>
                        <xsl:when test="onSale = \'true\'">
                            <xsl:text>SALE: $</xsl:text>
                            <xsl:value-of select="format-number(price, \'#,##0.00\')"/>
                            <xsl:text> (</xsl:text>
                            <xsl:value-of select="format-number(discount, \'0\')"/>
                            <xsl:text>% OFF)&#10;</xsl:text>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:text>Price: $</xsl:text>
                            <xsl:value-of select="format-number(price, \'#,##0.00\')"/>
                            <xsl:text>&#10;</xsl:text>
                        </xsl:otherwise>
                    </xsl:choose>

                    <xsl:text>  </xsl:text>
                    <xsl:choose>
                        <xsl:when test="stock &gt; 100">
                            <xsl:text>Stock: HIGH (</xsl:text>
                            <xsl:value-of select="stock"/>
                            <xsl:text>)&#10;</xsl:text>
                        </xsl:when>
                        <xsl:when test="stock &gt; 0">
                            <xsl:text>Stock: LOW (</xsl:text>
                            <xsl:value-of select="stock"/>
                            <xsl:text>)&#10;</xsl:text>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:text>Stock: OUT OF STOCK&#10;</xsl:text>
                        </xsl:otherwise>
                    </xsl:choose>

                    <xsl:text>  ---&#10;</xsl:text>
                </xsl:for-each>
                <xsl:text>&#10;</xsl:text>
            </xsl:if>
        </xsl:for-each>
    </xsl:template>
</xsl:stylesheet>');

$processor = new XSLTProcessor();
$processor->importStylesheet($xslt);

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    // Create XML from PHP array (this is the overhead!)
    $xml = new DOMDocument('1.0', 'UTF-8');
    $catalog = $xml->createElement('catalog');
    $xml->appendChild($catalog);

    foreach ($categories as $category) {
        $catNode = $xml->createElement('category');
        $catNode->appendChild($xml->createElement('id', (string)$category['id']));
        $catNode->appendChild($xml->createElement('name', $category['name']));
        $catNode->appendChild($xml->createElement('active', $category['active'] ? 'true' : 'false'));

        $productsNode = $xml->createElement('products');
        foreach ($category['products'] as $product) {
            $productNode = $xml->createElement('product');
            $productNode->appendChild($xml->createElement('id', (string)$product['id']));
            $productNode->appendChild($xml->createElement('sku', $product['sku']));
            $productNode->appendChild($xml->createElement('name', $product['name']));
            $productNode->appendChild($xml->createElement('price', (string)$product['price']));
            $productNode->appendChild($xml->createElement('stock', (string)$product['stock']));
            $productNode->appendChild($xml->createElement('onSale', $product['onSale'] ? 'true' : 'false'));
            $productNode->appendChild($xml->createElement('discount', (string)$product['discount']));
            $productNode->appendChild($xml->createElement('featured', $product['featured'] ? 'true' : 'false'));
            $productsNode->appendChild($productNode);
        }
        $catNode->appendChild($productsNode);
        $catalog->appendChild($catNode);
    }

    // Transform
    $result = $processor->transformToXML($xml);
}
$xsltTime = (microtime(true) - $start) * 1000;

// === XSLT BENCHMARK (transformation only, for reference) ===
// Create XML once
$xml = new DOMDocument('1.0', 'UTF-8');
$catalog = $xml->createElement('catalog');
$xml->appendChild($catalog);

foreach ($categories as $category) {
    $catNode = $xml->createElement('category');
    $catNode->appendChild($xml->createElement('id', (string)$category['id']));
    $catNode->appendChild($xml->createElement('name', $category['name']));
    $catNode->appendChild($xml->createElement('active', $category['active'] ? 'true' : 'false'));

    $productsNode = $xml->createElement('products');
    foreach ($category['products'] as $product) {
        $productNode = $xml->createElement('product');
        $productNode->appendChild($xml->createElement('id', (string)$product['id']));
        $productNode->appendChild($xml->createElement('sku', $product['sku']));
        $productNode->appendChild($xml->createElement('name', $product['name']));
        $productNode->appendChild($xml->createElement('price', (string)$product['price']));
        $productNode->appendChild($xml->createElement('stock', (string)$product['stock']));
        $productNode->appendChild($xml->createElement('onSale', $product['onSale'] ? 'true' : 'false'));
        $productNode->appendChild($xml->createElement('discount', (string)$product['discount']));
        $productNode->appendChild($xml->createElement('featured', $product['featured'] ? 'true' : 'false'));
        $productsNode->appendChild($productNode);
    }
    $catNode->appendChild($productsNode);
    $catalog->appendChild($catNode);
}

$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $result = $processor->transformToXML($xml);
}
$xsltOnlyTime = (microtime(true) - $start) * 1000;

// === RESULTS ===
echo "\n";
echo "Results:\n";
echo "========\n\n";

echo "Tsuku (works directly with arrays):\n";
echo "  Total time: " . number_format($tsukuTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($tsukuTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($tsukuTime / 1000), 2) . " renders/sec\n";
echo "  Products/sec: " . number_format(($totalProducts * $iterations) / ($tsukuTime / 1000)) . "\n\n";

echo "XSLT (transformation only, pre-created XML):\n";
echo "  Total time: " . number_format($xsltOnlyTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($xsltOnlyTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xsltOnlyTime / 1000), 2) . " renders/sec\n";
echo "  Products/sec: " . number_format(($totalProducts * $iterations) / ($xsltOnlyTime / 1000)) . "\n\n";

echo "XSLT (FAIR: includes XML creation from arrays):\n";
echo "  Total time: " . number_format($xsltTime, 2) . " ms\n";
echo "  Per iteration: " . number_format($xsltTime / $iterations, 2) . " ms\n";
echo "  Throughput: " . number_format($iterations / ($xsltTime / 1000), 2) . " renders/sec\n";
echo "  Products/sec: " . number_format(($totalProducts * $iterations) / ($xsltTime / 1000)) . "\n";
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
echo "With 5,000 products and nested conditionals:\n";
if ($speedupVsFair < 1) {
    echo "✓ Tsuku is FASTER when including XML creation overhead\n";
} else if ($speedupVsFair < 1.5) {
    echo "≈ Performance is nearly identical (< 50% difference)\n";
    echo "✓ Tsuku offers MUCH better developer experience\n";
    echo "✓ Tsuku is easier to maintain and debug\n";
} else {
    echo "XSLT is faster, but:\n";
    echo "  - Requires " . number_format((($xsltTime - $xsltOnlyTime) / $xsltTime) * 100, 1) . "% overhead for XML creation\n";
    echo "  - Much more complex to write and maintain\n";
    echo "  - Tsuku offers better developer experience\n";
}

echo "\nPer Product Performance:\n";
echo "  Tsuku: " . number_format(($tsukuTime / $iterations / $totalProducts) * 1000000, 2) . " μs/product\n";
echo "  XSLT (fair): " . number_format(($xsltTime / $iterations / $totalProducts) * 1000000, 2) . " μs/product\n";
