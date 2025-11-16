<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "=== Magento-Style Widget Syntax ===\n\n";

$tsuku = new Tsuku();

// Register widget functions - simpler approach
$tsuku->registerFunction('product_widget', function(string $category, int $limit = 5): string {
    return renderProductListWidget(['category' => $category, 'limit' => (string)$limit]);
});

$tsuku->registerFunction('banner_widget', function(string $title, string $image, string $link = '#'): string {
    return renderBannerWidget(['title' => $title, 'image' => $image, 'link' => $link]);
});

$tsuku->registerFunction('newsletter_widget', function(string $title = 'Subscribe', string $buttonText = 'Subscribe'): string {
    return renderNewsletterWidget(['title' => $title, 'button_text' => $buttonText]);
});

function renderProductListWidget(array $params): string
{
    $category = $params['category'] ?? 'all';
    $limit = $params['limit'] ?? '5';

    return <<<HTML
    <div class="widget-product-list">
        <h3>Products in category: {$category}</h3>
        <p>Showing {$limit} products</p>
        <!-- Product list would be dynamically loaded here -->
    </div>
    HTML;
}

function renderBannerWidget(array $params): string
{
    $title = $params['title'] ?? 'Banner';
    $image = $params['image'] ?? '/default-banner.jpg';
    $link = $params['link'] ?? '#';

    return <<<HTML
    <div class="widget-banner">
        <a href="{$link}">
            <img src="{$image}" alt="{$title}">
        </a>
    </div>
    HTML;
}

function renderNewsletterWidget(array $params): string
{
    $title = $params['title'] ?? 'Subscribe to Newsletter';
    $buttonText = $params['button_text'] ?? 'Subscribe';

    return <<<HTML
    <div class="widget-newsletter">
        <h4>{$title}</h4>
        <form method="post" action="/newsletter/subscribe">
            <input type="email" name="email" placeholder="Your email" required>
            <button type="submit">{$buttonText}</button>
        </form>
    </div>
    HTML;
}

// Example 1: Product List Widget
echo "1. Product List Widget:\n";
$template1 = '@product_widget("Electronics", 10)';
$result1 = $tsuku->process($template1, []);
echo "$result1\n\n";

// Example 2: Banner Widget
echo "2. Banner Widget:\n";
$template2 = '@banner_widget("Summer Sale", "/images/summer.jpg", "/sale")';
$result2 = $tsuku->process($template2, []);
echo "$result2\n\n";

// Example 3: Newsletter Widget
echo "3. Newsletter Widget:\n";
$template3 = '@newsletter_widget("Stay Updated", "Join Now")';
$result3 = $tsuku->process($template3, []);
echo "$result3\n\n";

// Example 4: Using widgets in templates with conditionals
echo "4. Conditional Widget Rendering:\n";
$template4 = '
<div class="sidebar">
@if(show_newsletter)
@newsletter_widget("Subscribe Now")
@end

@if(featured_category)
@product_widget(featured_category, 5)
@end
</div>
';

$result4 = $tsuku->process($template4, [
    'show_newsletter' => true,
    'featured_category' => 'Electronics',
]);
echo "$result4\n\n";

// Example 5: Alternative syntax using custom directive (more Magento-like)
echo "5. Custom Directive for Widget (Magento-style):\n\n";

// Register a custom directive that handles widget syntax more elegantly
$tsuku->registerFunction('cms_block', function(string $id): string {
    // Simulate loading CMS block by ID
    $blocks = [
        'header-promo' => '<div class="promo">Free Shipping on Orders Over $50!</div>',
        'footer-info' => '<div class="footer-info">© 2024 My Store. All rights reserved.</div>',
        'sidebar-ad' => '<div class="ad"><img src="/ads/special-offer.jpg"></div>',
    ];

    return $blocks[$id] ?? "<!-- CMS Block '{$id}' not found -->";
});

$template5 = '
<header>
    @cms_block("header-promo")
</header>

<main>
    <!-- Main content here -->
</main>

<aside>
    @cms_block("sidebar-ad")
</aside>

<footer>
    @cms_block("footer-info")
</footer>
';

$result5 = $tsuku->process($template5, []);
echo "$result5\n";

echo "\n=== Summary ===\n";
echo "Custom functions allow you to:\n";
echo "1. Create widget-like syntax similar to Magento\n";
echo "2. Parse complex attribute strings\n";
echo "3. Render dynamic components\n";
echo "4. Integrate with your application logic\n";
echo "5. Create reusable template components\n";
