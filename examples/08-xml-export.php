<?php

/**
 * XML Export Examples
 *
 * Demonstrates XML generation with proper escaping, formatting,
 * and real-world scenarios (product feeds, sitemaps, RSS, SOAP).
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

echo "=== XML EXPORT EXAMPLES ===\n\n";

// Example 1: Product Catalog XML Feed
echo "1. Product Catalog XML Feed\n";
echo str_repeat('-', 60) . "\n";

$template1 = '<?xml version="1.0" encoding="UTF-8"?>
<catalog>
@for(products as product)
  <product id="{product.id}">
    <sku>@xml(product.sku)</sku>
    <name>@xml(product.name)</name>
    <description>@xml(product.description)</description>
    <price currency="USD">{product.price}</price>
    <stock>{product.stock}</stock>
    <status>@xml(product.status)</status>
@if(product.images)
    <images>
@for(product.images as image)
      <image>@xml(image)</image>
@end
    </images>
@end
  </product>
@end
</catalog>';

$data1 = [
    'products' => [
        [
            'id' => 1,
            'sku' => 'WID-001',
            'name' => 'Premium Widget "Pro"',
            'description' => 'A <premium> widget with advanced features & capabilities',
            'price' => 29.99,
            'stock' => 100,
            'status' => 'active',
            'images' => [
                'https://example.com/images/widget-1.jpg',
                'https://example.com/images/widget-2.jpg',
            ]
        ],
        [
            'id' => 2,
            'sku' => 'GAD-002',
            'name' => 'Gadget <Professional>',
            'description' => 'Professional gadget for serious users',
            'price' => 599.00,
            'stock' => 25,
            'status' => 'active',
            'images' => []
        ],
    ]
];

echo $tsuku->process($template1, $data1);
echo "\n\n";

// Example 2: Google Shopping Feed
echo "2. Google Shopping Feed\n";
echo str_repeat('-', 60) . "\n";

$template2 = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title>My Store Product Feed</title>
    <link>https://example.com</link>
    <description>Product feed for Google Shopping</description>
@for(products as product)
    <item>
      <g:id>@xml(product.id)</g:id>
      <g:title>@xml(product.name)</g:title>
      <g:description>@xml(product.description)</g:description>
      <g:link>@xml(product.url)</g:link>
      <g:image_link>@xml(product.image)</g:image_link>
      <g:price>{product.price} USD</g:price>
      <g:availability>@match(product.stock) @case(0) out of stock @default in stock @end</g:availability>
      <g:condition>new</g:condition>
      <g:brand>@xml(product.brand)</g:brand>
      <g:gtin>@xml(product.gtin)</g:gtin>
    </item>
@end
  </channel>
</rss>';

$data2 = [
    'products' => [
        [
            'id' => 'WID-001',
            'name' => 'Premium Widget "Pro" 2025',
            'description' => 'The best widget on the market - featuring <advanced> technology',
            'url' => 'https://example.com/products/premium-widget',
            'image' => 'https://example.com/images/widget.jpg',
            'price' => 29.99,
            'stock' => 100,
            'brand' => 'WidgetCo',
            'gtin' => '1234567890123'
        ],
        [
            'id' => 'GAD-002',
            'name' => 'Professional Gadget & Accessories',
            'description' => 'Professional-grade gadget with all the features you need',
            'url' => 'https://example.com/products/pro-gadget',
            'image' => 'https://example.com/images/gadget.jpg',
            'price' => 599.00,
            'stock' => 0,
            'brand' => 'GadgetPro',
            'gtin' => '9876543210987'
        ],
    ]
];

echo $tsuku->process($template2, $data2);
echo "\n\n";

// Example 3: Sitemap XML
echo "3. XML Sitemap\n";
echo str_repeat('-', 60) . "\n";

$template3 = '<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@for(pages as page)
  <url>
    <loc>@xml(page.url)</loc>
    <lastmod>@date("Y-m-d", page.modified)</lastmod>
    <changefreq>@xml(page.changefreq)</changefreq>
    <priority>{page.priority}</priority>
  </url>
@end
</urlset>';

$data3 = [
    'pages' => [
        [
            'url' => 'https://example.com/',
            'modified' => strtotime('2025-01-15'),
            'changefreq' => 'daily',
            'priority' => 1.0
        ],
        [
            'url' => 'https://example.com/products',
            'modified' => strtotime('2025-01-14'),
            'changefreq' => 'daily',
            'priority' => 0.8
        ],
        [
            'url' => 'https://example.com/about',
            'modified' => strtotime('2025-01-01'),
            'changefreq' => 'monthly',
            'priority' => 0.5
        ],
    ]
];

echo $tsuku->process($template3, $data3);
echo "\n\n";

// Example 4: RSS Feed
echo "4. RSS Feed\n";
echo str_repeat('-', 60) . "\n";

$template4 = '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>@xml(feed.title)</title>
    <link>@xml(feed.url)</link>
    <description>@xml(feed.description)</description>
    <language>en-us</language>
@for(feed.items as item)
    <item>
      <title>@xml(item.title)</title>
      <link>@xml(item.url)</link>
      <description>@xml(item.description)</description>
      <pubDate>@date("D, d M Y H:i:s O", item.published)</pubDate>
      <guid>@xml(item.url)</guid>
@if(item.author)
      <author>@xml(item.author)</author>
@end
@if(item.categories)
      @for(item.categories as category)
      <category>@xml(category)</category>
      @end
@end
    </item>
@end
  </channel>
</rss>';

$data4 = [
    'feed' => [
        'title' => 'My Blog & News',
        'url' => 'https://example.com',
        'description' => 'Latest articles, news & updates from our blog',
        'items' => [
            [
                'title' => 'Introducing New Features in 2025',
                'url' => 'https://example.com/blog/new-features-2025',
                'description' => 'We\'re excited to announce <amazing> new features coming this year',
                'published' => strtotime('2025-01-15 10:00:00'),
                'author' => 'john@example.com (John Doe)',
                'categories' => ['Product Updates', 'News']
            ],
            [
                'title' => 'How to Use Our API',
                'url' => 'https://example.com/blog/api-guide',
                'description' => 'A comprehensive guide to using our API & SDK',
                'published' => strtotime('2025-01-10 14:30:00'),
                'author' => 'jane@example.com (Jane Smith)',
                'categories' => ['Tutorials', 'Developers']
            ],
        ]
    ]
];

echo $tsuku->process($template4, $data4);
echo "\n\n";

// Example 5: SOAP-style Web Service Response
echo "5. SOAP-style Web Service Response\n";
echo str_repeat('-', 60) . "\n";

$template5 = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GetOrdersResponse>
      <Status>@xml(response.status)</Status>
      <Message>@xml(response.message)</Message>
@if(response.orders)
      <Orders>
@for(response.orders as order)
        <Order>
          <OrderID>@xml(order.id)</OrderID>
          <CustomerName>@xml(order.customer)</CustomerName>
          <Total currency="USD">{order.total}</Total>
          <Status>@xml(order.status)</Status>
          <Items>
            @for(order.items as item)
            <Item>
              <ProductID>@xml(item.product_id)</ProductID>
              <Quantity>{item.quantity}</Quantity>
              <Price>{item.price}</Price>
            </Item>
            @end
          </Items>
        </Order>
@end
      </Orders>
@end
    </GetOrdersResponse>
  </soap:Body>
</soap:Envelope>';

$data5 = [
    'response' => [
        'status' => 'success',
        'message' => 'Orders retrieved successfully',
        'orders' => [
            [
                'id' => 'ORD-1001',
                'customer' => 'John Doe & Associates',
                'total' => 299.99,
                'status' => 'completed',
                'items' => [
                    ['product_id' => 'WID-001', 'quantity' => 2, 'price' => 29.99],
                    ['product_id' => 'GAD-002', 'quantity' => 1, 'price' => 240.01],
                ]
            ],
        ]
    ]
];

echo $tsuku->process($template5, $data5);
echo "\n\n";

echo "=== All XML examples completed! ===\n";
