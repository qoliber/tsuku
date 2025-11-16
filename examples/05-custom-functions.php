<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

echo "=== Custom Functions Examples ===\n\n";

$tsuku = new Tsuku();

// Example 1: Register a simple custom function
echo "1. Simple Custom Function:\n";
$tsuku->registerFunction('greet', function(string $name): string {
    return "Hello, {$name}!";
});

$template = 'Welcome message: @greet(user.name)';
$result = $tsuku->process($template, ['user' => ['name' => 'John']]);
echo "$result\n\n";

// Example 2: Register function with multiple arguments
echo "2. Multi-argument Function:\n";
$tsuku->registerFunction('currency', function(float $amount, string $currency = 'USD'): string {
    return match($currency) {
        'USD' => '$' . number_format($amount, 2),
        'EUR' => '€' . number_format($amount, 2),
        'GBP' => '£' . number_format($amount, 2),
        default => $currency . ' ' . number_format($amount, 2),
    };
});

$template2 = 'Total: @currency(product.price, "EUR")';
$result2 = $tsuku->process($template2, ['product' => ['price' => 99.99]]);
echo "$result2\n\n";

// Example 3: Register function that returns HTML
echo "3. HTML-generating Function:\n";
$tsuku->registerFunction('badge', function(string $text, string $color = 'blue'): string {
    return "<span class=\"badge badge-{$color}\">{$text}</span>";
});

$template3 = 'Status: @badge(status, "green")';
$result3 = $tsuku->process($template3, ['status' => 'Active']);
echo "$result3\n\n";

// Example 4: Register function that works with arrays
echo "4. Array-processing Function:\n";
$tsuku->registerFunction('pluck', function(array $items, string $key): array {
    return array_column($items, $key);
});

$template4 = 'Names: @join(@pluck(users, "name"), ", ")';
$result4 = $tsuku->process($template4, [
    'users' => [
        ['name' => 'Alice', 'age' => 30],
        ['name' => 'Bob', 'age' => 25],
        ['name' => 'Charlie', 'age' => 35],
    ],
]);
echo "$result4\n\n";

// Example 5: Register function with variable arguments
echo "5. Variadic Function:\n";
$tsuku->registerFunction('format_list', function(...$items): string {
    if (count($items) === 0) {
        return '';
    }
    if (count($items) === 1) {
        return $items[0];
    }
    $last = array_pop($items);
    return implode(', ', $items) . ' and ' . $last;
});

$template5 = 'Ingredients: @format_list("flour", "sugar", "eggs", "milk")';
$result5 = $tsuku->process($template5, []);
echo "$result5\n\n";

// Example 6: Register function that uses closures
echo "6. Closure-based Function with External Data:\n";
$config = ['site_name' => 'My Website', 'domain' => 'example.com'];

$tsuku->registerFunction('site_url', function(string $path = '') use ($config): string {
    return 'https://' . $config['domain'] . '/' . ltrim($path, '/');
});

$template6 = 'Visit: @site_url("products")';
$result6 = $tsuku->process($template6, []);
echo "$result6\n\n";

// Example 7: Register function that formats dates
echo "7. Date Formatting Function:\n";
$tsuku->registerFunction('format_date', function(string|int $date, string $format = 'Y-m-d'): string {
    $timestamp = is_numeric($date) ? (int)$date : strtotime($date);
    return date($format, $timestamp);
});

$template7 = 'Published: @format_date(article.published_at, "F j, Y")';
$result7 = $tsuku->process($template7, ['article' => ['published_at' => '2024-01-15']]);
echo "$result7\n\n";

// Example 8: Chaining custom functions
echo "8. Chained Custom Functions:\n";
$tsuku->registerFunction('slug', function(string $text): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim($text)));
});

$template8 = 'URL: /blog/@slug(@upper(article.title))';
$result8 = $tsuku->process($template8, ['article' => ['title' => 'Hello World!']]);
echo "$result8\n\n";
