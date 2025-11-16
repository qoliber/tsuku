<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

echo "=== Basic Tsuku Examples ===\n\n";

// Example 1: Simple variable interpolation
echo "1. Variable Interpolation:\n";
$template = 'Hello, {name}! Welcome to {place}.';
$result = $tsuku->process($template, ['name' => 'World', 'place' => 'Tsuku']);
echo "$result\n\n";

// Example 2: For loop
echo "2. For Loop:\n";
$template = '@for(items as item)
- {item}
@end';
$result = $tsuku->process($template, ['items' => ['Apple', 'Banana', 'Orange']]);
echo "$result\n";

// Example 3: For loop with key
echo "3. For Loop with Key:\n";
$template = '@for(users as user, id)
User #{id}: {user}
@end';
$result = $tsuku->process($template, ['users' => ['Alice', 'Bob', 'Charlie']]);
echo "$result\n";

// Example 4: Conditional
echo "4. Conditional (@if):\n";
$template = '@if(stock > 0)
In Stock
@else
Out of Stock
@end';
$result1 = $tsuku->process($template, ['stock' => 10]);
$result2 = $tsuku->process($template, ['stock' => 0]);
echo "With stock: $result1";
echo "No stock: $result2\n";

// Example 5: Unless directive
echo "5. Unless Directive:\n";
$template = '@unless(error)
Success!
@else
Error: {error}
@end';
$result1 = $tsuku->process($template, ['error' => '']);
$result2 = $tsuku->process($template, ['error' => 'Something went wrong']);
echo "No error: $result1";
echo "With error: $result2\n";

// Example 6: Nested data access
echo "6. Nested Data Access:\n";
$template = 'User: {user.name}, Location: {user.address.city}, {user.address.country}';
$result = $tsuku->process($template, [
    'user' => [
        'name' => 'John Doe',
        'address' => [
            'city' => 'New York',
            'country' => 'USA',
        ],
    ],
]);
echo "$result\n\n";
