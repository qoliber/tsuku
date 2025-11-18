<?php

/**
 * HTML Generation Examples
 *
 * Demonstrates HTML generation from complex objects with proper
 * XSS protection, accessibility, and modern HTML5 patterns.
 */

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

echo "=== HTML GENERATION EXAMPLES ===\n\n";

// Example 1: Product Card Components
echo "1. Product Card with Complex Data\n";
echo str_repeat('-', 60) . "\n";

$template1 = '<div class="product-grid">
@for(products as product)
  <article class="product-card" data-product-id="{product.id}">
    <div class="product-image">
      <img src="@html(product.image.url)"
           alt="@html(product.image.alt)"
           width="{product.image.width}"
           height="{product.image.height}">
@if(product.badge)
      <span class="badge badge-@html(product.badge.type)">@html(product.badge.text)</span>
@end
    </div>
    <div class="product-content">
      <h3 class="product-title">@html(product.name)</h3>
      <p class="product-description">@html(product.description)</p>
      <div class="product-meta">
        <div class="product-price">
@if(product.sale_price)
          <span class="price-old">$@number(product.regular_price, 2)</span>
          <span class="price-sale">$@number(product.sale_price, 2)</span>
@else
          <span class="price">$@number(product.regular_price, 2)</span>
@end
        </div>
        <div class="product-rating">
@for(product.rating.stars as star, index)
          <span class="star @?{star "filled" : "empty"}">★</span>
@end
          <span class="rating-count">({product.rating.count})</span>
        </div>
      </div>
      <div class="product-actions">
@if(product.stock > 0)
        <button class="btn btn-primary" data-action="add-to-cart" data-product-id="{product.id}">
          Add to Cart
        </button>
        <p class="stock-status in-stock">@?{product.stock < 10 "Only " : ""}{product.stock} in stock</p>
@else
        <button class="btn btn-secondary" disabled>Out of Stock</button>
        <p class="stock-status out-of-stock">Notify me when available</p>
@end
      </div>
    </div>
  </article>
@end
</div>';

$data1 = [
    'products' => [
        [
            'id' => 'WID-001',
            'name' => 'Premium Widget "Pro Edition"',
            'description' => 'A <premium> widget with advanced features & capabilities for professional use',
            'regular_price' => 129.99,
            'sale_price' => 99.99,
            'stock' => 5,
            'image' => [
                'url' => 'https://example.com/images/widget-pro.jpg',
                'alt' => 'Premium Widget Pro Edition - Professional Grade',
                'width' => 400,
                'height' => 400
            ],
            'badge' => ['type' => 'sale', 'text' => 'SALE'],
            'rating' => [
                'stars' => [true, true, true, true, false],
                'count' => 128
            ]
        ],
        [
            'id' => 'GAD-002',
            'name' => 'Professional Gadget <Ultimate>',
            'description' => 'The ultimate gadget for professionals who demand the best',
            'regular_price' => 599.00,
            'sale_price' => null,
            'stock' => 0,
            'image' => [
                'url' => 'https://example.com/images/gadget-ultimate.jpg',
                'alt' => 'Professional Gadget Ultimate Edition',
                'width' => 400,
                'height' => 400
            ],
            'badge' => ['type' => 'new', 'text' => 'NEW'],
            'rating' => [
                'stars' => [true, true, true, true, true],
                'count' => 256
            ]
        ],
    ]
];

echo $tsuku->process($template1, $data1);
echo "\n\n";

// Example 2: User Profile Dashboard
echo "2. User Profile Dashboard\n";
echo str_repeat('-', 60) . "\n";

$template2 = '<div class="dashboard">
  <div class="profile-header">
    <img src="@html(user.avatar)" alt="@html(user.name)" class="avatar">
    <div class="profile-info">
      <h1>@html(user.name)</h1>
      <p class="bio">@html(user.bio)</p>
      <div class="stats">
@for(user.stats as stat)
        <div class="stat">
          <span class="stat-value">{stat.value}</span>
          <span class="stat-label">@html(stat.label)</span>
        </div>
@end
      </div>
    </div>
  </div>

  <div class="profile-content">
    <section class="orders">
      <h2>Recent Orders</h2>
      <table class="orders-table">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
@for(user.orders as order)
          <tr>
            <td><a href="/orders/@html(order.id)">@html(order.id)</a></td>
            <td>@date("M d, Y", order.date)</td>
            <td>$@number(order.total, 2)</td>
            <td>
              <span class="status status-@html(order.status)">
                @match(order.status)
@case("completed") ✓ Completed
@case("pending") ⏳ Pending
@case("cancelled") ✗ Cancelled
@default Unknown
@end
              </span>
            </td>
          </tr>
@end
        </tbody>
      </table>
    </section>

    <section class="addresses">
      <h2>Saved Addresses</h2>
@for(user.addresses as address)
      <div class="address-card @?{address.is_default "default" : ""}">
        <h3>@html(address.label)</h3>
        <p>@html(address.street)<br>
           @html(address.city), @html(address.state) @html(address.zip)<br>
           @html(address.country)</p>
@if(address.is_default)
        <span class="badge">Default</span>
@end
      </div>
@end
    </section>
  </div>
</div>';

$data2 = [
    'user' => [
        'name' => 'John Doe <Admin>',
        'bio' => 'Software developer & tech enthusiast. Love building <amazing> things!',
        'avatar' => 'https://example.com/avatars/john.jpg',
        'stats' => [
            ['value' => 42, 'label' => 'Orders'],
            ['value' => 128, 'label' => 'Reviews'],
            ['value' => 5, 'label' => 'Wishlist']
        ],
        'orders' => [
            [
                'id' => 'ORD-1001',
                'date' => strtotime('2025-01-15'),
                'total' => 299.99,
                'status' => 'completed'
            ],
            [
                'id' => 'ORD-1002',
                'date' => strtotime('2025-01-10'),
                'total' => 149.50,
                'status' => 'pending'
            ],
        ],
        'addresses' => [
            [
                'label' => 'Home',
                'street' => '123 Main St, Apt #4',
                'city' => 'San Francisco',
                'state' => 'CA',
                'zip' => '94102',
                'country' => 'USA',
                'is_default' => true
            ],
            [
                'label' => 'Office',
                'street' => '456 Business Ave, Suite 200',
                'city' => 'New York',
                'state' => 'NY',
                'zip' => '10001',
                'country' => 'USA',
                'is_default' => false
            ],
        ]
    ]
];

echo $tsuku->process($template2, $data2);
echo "\n\n";

// Example 3: Blog Posts with Author Info
echo "3. Blog Article List\n";
echo str_repeat('-', 60) . "\n";

$template3 = '<div class="blog-posts">
@for(posts as post)
  <article class="blog-post">
    <header class="post-header">
@if(post.featured_image)
      <img src="@html(post.featured_image.url)"
           alt="@html(post.featured_image.alt)"
           class="featured-image">
@end
      <h2><a href="@html(post.url)">@html(post.title)</a></h2>
      <div class="post-meta">
        <div class="author">
          <img src="@html(post.author.avatar)" alt="@html(post.author.name)" class="author-avatar">
          <span>By <a href="@html(post.author.url)">@html(post.author.name)</a></span>
        </div>
        <time datetime="@date("Y-m-d", post.published)">@date("F j, Y", post.published)</time>
        <span class="read-time">{post.read_time} min read</span>
      </div>
    </header>
    <div class="post-content">
      <p>@html(post.excerpt)</p>
@if(post.tags)
      <div class="post-tags">
@for(post.tags as tag)
        <a href="/tag/@url(tag.slug)" class="tag">@html(tag.name)</a>
@end
      </div>
@end
    </div>
    <footer class="post-footer">
      <div class="post-stats">
        <span>👁 {post.views} views</span>
        <span>💬 {post.comments} comments</span>
        <span>❤️ {post.likes} likes</span>
      </div>
      <a href="@html(post.url)" class="read-more">Read More →</a>
    </footer>
  </article>
@end
</div>';

$data3 = [
    'posts' => [
        [
            'title' => 'Getting Started with Modern PHP & Best Practices',
            'excerpt' => 'Learn how to write clean, maintainable PHP code using <modern> techniques and best practices...',
            'url' => 'https://example.com/blog/modern-php',
            'published' => strtotime('2025-01-15'),
            'read_time' => 8,
            'views' => 1250,
            'comments' => 42,
            'likes' => 128,
            'featured_image' => [
                'url' => 'https://example.com/images/php-guide.jpg',
                'alt' => 'Modern PHP Development Guide'
            ],
            'author' => [
                'name' => 'Jane Smith <Tech Lead>',
                'avatar' => 'https://example.com/avatars/jane.jpg',
                'url' => 'https://example.com/author/jane-smith'
            ],
            'tags' => [
                ['name' => 'PHP', 'slug' => 'php'],
                ['name' => 'Best Practices', 'slug' => 'best-practices'],
                ['name' => 'Tutorial', 'slug' => 'tutorial']
            ]
        ],
        [
            'title' => 'Building RESTful APIs with PHP 8.1+',
            'excerpt' => 'A comprehensive guide to building modern REST APIs using PHP 8.1+ features & frameworks...',
            'url' => 'https://example.com/blog/rest-api-php',
            'published' => strtotime('2025-01-10'),
            'read_time' => 12,
            'views' => 892,
            'comments' => 28,
            'likes' => 95,
            'featured_image' => null,
            'author' => [
                'name' => 'Bob Johnson',
                'avatar' => 'https://example.com/avatars/bob.jpg',
                'url' => 'https://example.com/author/bob-johnson'
            ],
            'tags' => [
                ['name' => 'PHP', 'slug' => 'php'],
                ['name' => 'API', 'slug' => 'api'],
                ['name' => 'REST', 'slug' => 'rest']
            ]
        ],
    ]
];

echo $tsuku->process($template3, $data3);
echo "\n\n";

// Example 4: E-commerce Checkout Form
echo "4. Checkout Form with Validation\n";
echo str_repeat('-', 60) . "\n";

$template4 = '<form class="checkout-form" method="post" action="/checkout">
  <section class="form-section">
    <h2>Shipping Information</h2>
    <div class="form-grid">
@for(form.shipping_fields as field)
      <div class="form-group @?{field.required "required" : ""}">
        <label for="@html(field.name)">
          @html(field.label)
@if(field.required)
          <span class="required">*</span>
@end
        </label>
@match(field.type)
@case("text", "email", "tel")
        <input type="@html(field.type)"
               id="@html(field.name)"
               name="@html(field.name)"
               placeholder="@html(field.placeholder)"
               @?{field.required "required" : ""}>
@case("select")
        <select id="@html(field.name)" name="@html(field.name)" @?{field.required "required" : ""}>
          <option value="">@html(field.placeholder)</option>
@for(field.options as option)
          <option value="@html(option.value)">@html(option.label)</option>
@end
        </select>
@case("textarea")
        <textarea id="@html(field.name)"
                  name="@html(field.name)"
                  placeholder="@html(field.placeholder)"
                  rows="3"></textarea>
@end
@if(field.help_text)
        <small class="help-text">@html(field.help_text)</small>
@end
      </div>
@end
    </div>
  </section>

  <section class="form-section">
    <h2>Order Summary</h2>
    <table class="order-summary">
@for(form.cart_items as item)
      <tr>
        <td>@html(item.name) × {item.quantity}</td>
        <td class="text-right">$@number(item.price * item.quantity, 2)</td>
      </tr>
@end
      <tr class="subtotal">
        <td>Subtotal</td>
        <td class="text-right">$@number(form.subtotal, 2)</td>
      </tr>
      <tr class="shipping">
        <td>Shipping</td>
        <td class="text-right">$@number(form.shipping, 2)</td>
      </tr>
      <tr class="tax">
        <td>Tax</td>
        <td class="text-right">$@number(form.tax, 2)</td>
      </tr>
      <tr class="total">
        <td><strong>Total</strong></td>
        <td class="text-right"><strong>$@number(form.total, 2)</strong></td>
      </tr>
    </table>
  </section>

  <button type="submit" class="btn btn-primary btn-lg">Complete Order</button>
</form>';

$data4 = [
    'form' => [
        'shipping_fields' => [
            [
                'name' => 'full_name',
                'label' => 'Full Name',
                'type' => 'text',
                'placeholder' => 'John Doe',
                'required' => true,
                'help_text' => null
            ],
            [
                'name' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'placeholder' => 'john@example.com',
                'required' => true,
                'help_text' => 'We\'ll send order confirmation to this email'
            ],
            [
                'name' => 'phone',
                'label' => 'Phone Number',
                'type' => 'tel',
                'placeholder' => '+1 (555) 123-4567',
                'required' => true,
                'help_text' => null
            ],
            [
                'name' => 'country',
                'label' => 'Country',
                'type' => 'select',
                'placeholder' => 'Select Country',
                'required' => true,
                'help_text' => null,
                'options' => [
                    ['value' => 'US', 'label' => 'United States'],
                    ['value' => 'CA', 'label' => 'Canada'],
                    ['value' => 'UK', 'label' => 'United Kingdom']
                ]
            ],
            [
                'name' => 'notes',
                'label' => 'Order Notes',
                'type' => 'textarea',
                'placeholder' => 'Any special instructions?',
                'required' => false,
                'help_text' => 'Optional delivery instructions'
            ],
        ],
        'cart_items' => [
            ['name' => 'Premium Widget', 'quantity' => 2, 'price' => 29.99],
            ['name' => 'Gadget Pro', 'quantity' => 1, 'price' => 59.99],
        ],
        'subtotal' => 119.97,
        'shipping' => 9.99,
        'tax' => 12.00,
        'total' => 141.96
    ]
];

echo $tsuku->process($template4, $data4);
echo "\n\n";

echo "=== All HTML examples completed! ===\n";
