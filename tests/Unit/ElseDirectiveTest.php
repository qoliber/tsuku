<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class ElseDirectiveTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testIfWithElse(): void
    {
        $template = '@if(stock > 0)
In Stock
@else
Out of Stock
@end';

        $result = $this->tsuku->process($template, ['stock' => 10]);
        $this->assertStringContainsString('In Stock', $result);
        $this->assertStringNotContainsString('Out of Stock', $result);

        $result = $this->tsuku->process($template, ['stock' => 0]);
        $this->assertStringContainsString('Out of Stock', $result);
        $this->assertStringNotContainsString('In Stock', $result);
    }

    public function testUnlessWithElse(): void
    {
        $template = '@unless(error)
Success!
@else
Error: {error}
@end';

        $result = $this->tsuku->process($template, ['error' => '']);
        $this->assertStringContainsString('Success!', $result);
        $this->assertStringNotContainsString('Error:', $result);

        $result = $this->tsuku->process($template, ['error' => 'Failed']);
        $this->assertStringContainsString('Error: Failed', $result);
        $this->assertStringNotContainsString('Success!', $result);
    }

    public function testIfWithElseAndVariables(): void
    {
        $template = '@if(role == "admin")
Welcome Admin {name}!
@else
Welcome User {name}!
@end';

        $result = $this->tsuku->process($template, ['role' => 'admin', 'name' => 'John']);
        $this->assertStringContainsString('Welcome Admin John!', $result);

        $result = $this->tsuku->process($template, ['role' => 'user', 'name' => 'Jane']);
        $this->assertStringContainsString('Welcome User Jane!', $result);
    }

    public function testIfWithElseAndFunctions(): void
    {
        $template = '@if(@length(items) > 0)
Items: @join(items, ", ")
@else
No items available
@end';

        $result = $this->tsuku->process($template, ['items' => ['apple', 'banana', 'orange']]);
        $this->assertStringContainsString('Items: apple, banana, orange', $result);

        $result = $this->tsuku->process($template, ['items' => []]);
        $this->assertStringContainsString('No items available', $result);
    }

    public function testNestedIfWithElse(): void
    {
        $template = '@if(user)
@if(user.admin)
Admin Panel
@else
User Dashboard
@end
@else
Please Login
@end';

        $result = $this->tsuku->process($template, ['user' => ['admin' => true]]);
        $this->assertStringContainsString('Admin Panel', $result);

        $result = $this->tsuku->process($template, ['user' => ['admin' => false]]);
        $this->assertStringContainsString('User Dashboard', $result);

        $result = $this->tsuku->process($template, ['user' => null]);
        $this->assertStringContainsString('Please Login', $result);
    }

    public function testIfWithElseInLoop(): void
    {
        $template = '@for(products as product)
{product.name}: @if(product.stock > 0)
In Stock
@else
Out of Stock
@end
@end';

        $data = [
            'products' => [
                ['name' => 'Widget A', 'stock' => 10],
                ['name' => 'Widget B', 'stock' => 0],
                ['name' => 'Widget C', 'stock' => 5],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('Widget A:', $result);
        $this->assertStringContainsString('Widget B:', $result);
        $this->assertStringContainsString('Widget C:', $result);

        // Count occurrences
        $this->assertEquals(2, substr_count($result, 'In Stock'));
        $this->assertEquals(1, substr_count($result, 'Out of Stock'));
    }

    public function testIfWithoutElse(): void
    {
        // Make sure @if still works without @else
        $template = '@if(show)
Visible
@end';

        $result = $this->tsuku->process($template, ['show' => true]);
        $this->assertStringContainsString('Visible', $result);

        $result = $this->tsuku->process($template, ['show' => false]);
        $this->assertStringNotContainsString('Visible', $result);
        $this->assertEquals('', trim($result));
    }
}
