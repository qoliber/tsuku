<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "=== Number Formatting & Escaping Functions ===\n\n";

$tsuku = new Tsuku();

// Example 1: Number Formatting - Default (US style)
echo "1. Basic Number Formatting:\n";
$template1 = 'Price: $@number(price, 2)';
$result1 = $tsuku->process($template1, ['price' => 1234.567]);
echo "$result1\n\n";

// Example 2: Number Formatting - European style
echo "2. European Number Formatting:\n";
$template2 = 'Preis: @number(price, 2, ",", ".") €';
$result2 = $tsuku->process($template2, ['price' => 1234567.89]);
echo "$result2\n\n";

// Example 3: CSV Export with Proper Escaping
echo "3. CSV Export with Escaping:\n";
$template3 = 'SKU,Name,Description,Price
@for(products as product)
@csv(product.sku),@csv(product.name),@csv(product.description),$@number(product.price, 2)
@end';

$data3 = [
    'products' => [
        [
            'sku' => 'WID-001',
            'name' => 'Basic Widget',
            'description' => 'A simple widget',
            'price' => 29.99,
        ],
        [
            'sku' => 'GAD-002',
            'name' => 'Premium Gadget, Deluxe',
            'description' => 'A "premium" gadget with features',
            'price' => 1299.50,
        ],
        [
            'sku' => 'TOY-003',
            'name' => 'Super Toy',
            'description' => "Multi-line\ndescription here",
            'price' => 49.95,
        ],
    ],
];

$result3 = $tsuku->process($template3, $data3);
echo "$result3\n";

// Example 4: HTML with XSS Protection
echo "4. HTML Generation with XSS Protection:\n";
$template4 = '<div class="comment">
  <h3>@html(comment.author)</h3>
  <p>@html(comment.text)</p>
</div>';

$data4 = [
    'comment' => [
        'author' => 'John <script>alert("XSS")</script> Doe',
        'text' => 'This is a <strong>great</strong> product & I love it!',
    ],
];

$result4 = $tsuku->process($template4, $data4);
echo "$result4\n\n";

// Example 5: URL Encoding
echo "5. URL Encoding for Query Parameters:\n";
$template5 = '@for(searches as search)
https://example.com/search?q=@url(search.query)&category=@url(search.category)
@end';

$data5 = [
    'searches' => [
        ['query' => 'premium widgets', 'category' => 'Electronics & Gadgets'],
        ['query' => 'test & verify', 'category' => 'Tools/Equipment'],
    ],
];

$result5 = $tsuku->process($template5, $data5);
echo "$result5\n";

// Example 6: XML Generation with Escaping
echo "6. XML Generation with Escaping:\n";
$template6 = '<?xml version="1.0"?>
<products>
@for(products as product)
  <product id="{product.id}">
    <name>@xml(product.name)</name>
    <description>@xml(product.description)</description>
    <price>@number(product.price, 2)</price>
  </product>
@end
</products>';

$data6 = [
    'products' => [
        [
            'id' => '1',
            'name' => 'Widget & Gadget Bundle',
            'description' => 'Includes <premium> features & support',
            'price' => 199.99,
        ],
    ],
];

$result6 = $tsuku->process($template6, $data6);
echo "$result6\n";

// Example 7: Generic Escape Function
echo "7. Generic Escape Function:\n";
$template7 = 'HTML: @escape(text, "html")
XML: @escape(text, "xml")
URL: @escape(text, "url")
CSV: @escape(text, "csv")';

$data7 = ['text' => 'Hello, <World> & "More"'];
$result7 = $tsuku->process($template7, $data7);
echo "$result7\n\n";

// Example 8: E-commerce Invoice with Number Formatting
echo "8. E-commerce Invoice:\n";
$template8 = 'INVOICE #@number(invoice.id, 0, "", "")

Item                          Qty    Price      Total
@for(invoice.items as item)
{item.name}                   {item.qty}      $@number(item.price, 2)    $@number(item.qty * item.price, 2)
@end
                                     --------
                          Subtotal:  $@number(invoice.subtotal, 2)
                               Tax:  $@number(invoice.tax, 2)
                                     ========
                             TOTAL:  $@number(invoice.total, 2)
';

$data8 = [
    'invoice' => [
        'id' => 12345,
        'items' => [
            ['name' => 'Premium Widget', 'qty' => 2, 'price' => 1299.99],
            ['name' => 'Basic Gadget', 'qty' => 5, 'price' => 49.95],
        ],
        'subtotal' => 2849.73,
        'tax' => 227.98,
        'total' => 3077.71,
    ],
];

$result8 = $tsuku->process($template8, $data8);
echo "$result8\n";

echo "=== Summary ===\n";
echo "Number formatting functions:\n";
echo "- @number(value, decimals, decPoint, thousandsSep)\n";
echo "- @round(value, precision)\n";
echo "- @ceil(value), @floor(value), @abs(value)\n\n";
echo "Escaping functions:\n";
echo "- @html(text) - HTML escaping (XSS protection)\n";
echo "- @xml(text) - XML escaping\n";
echo "- @json(text) - JSON escaping\n";
echo "- @url(text) - URL encoding\n";
echo "- @csv(text) - CSV escaping with quotes\n";
echo "- @escape(text, type) - Generic escape function\n";
