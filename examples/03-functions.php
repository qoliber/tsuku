<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Qoliber\Tsuku\Tsuku;

$tsuku = new Tsuku();

echo "=== Built-in Functions ===\n\n";

// Example 1: String functions
echo "1. String Functions:\n";
$template = 'Upper: @upper(name), Lower: @lower(name)';
$result = $tsuku->process($template, ['name' => 'Hello World']);
echo "$result\n\n";

// Example 2: Array functions
echo "2. Array Functions:\n";
$template = 'Items: @join(items, ", ")';
$result = $tsuku->process($template, ['items' => ['Apple', 'Banana', 'Orange']]);
echo "$result\n\n";

// Example 3: Length function
echo "3. Length Function:\n";
$template = 'Total items: @length(items)';
$result = $tsuku->process($template, ['items' => ['A', 'B', 'C', 'D']]);
echo "$result\n\n";

// Example 4: Default value
echo "4. Default Value:\n";
$template = 'Name: @default(name, "Guest")';
$result1 = $tsuku->process($template, ['name' => '']);
$result2 = $tsuku->process($template, ['name' => 'John']);
echo "Empty: $result1\n";
echo "Provided: $result2\n\n";

// Example 5: Nested functions
echo "5. Nested Functions:\n";
$template = 'Result: @upper(@default(name, "guest"))';
$result = $tsuku->process($template, ['name' => '']);
echo "$result\n\n";

// Example 6: Functions in conditionals
echo "6. Functions in Conditionals:\n";
$template = '@if(@length(items) > 2)
Many items: @join(items, ", ")
@else
Few items
@end';
$result = $tsuku->process($template, ['items' => ['A', 'B', 'C', 'D']]);
echo "$result\n";

// Example 7: Functions with variables
echo "7. Functions with Variables:\n";
$template = 'Greeting: @upper(greeting), @join(names, " and ")!';
$result = $tsuku->process($template, [
    'greeting' => 'hello',
    'names' => ['Alice', 'Bob'],
]);
echo "$result\n\n";
