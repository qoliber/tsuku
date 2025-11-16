<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class HtmlTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateHtmlPage(): void
    {
        $template = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{page.title}</title>
</head>
<body>
    <h1>{page.heading}</h1>
    <p>{page.description}</p>
</body>
</html>';

        $data = [
            'page' => [
                'title' => 'Welcome',
                'heading' => 'Hello World',
                'description' => 'This is a test page.',
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('<title>Welcome</title>', $result);
        $this->assertStringContainsString('<h1>Hello World</h1>', $result);
        $this->assertStringContainsString('<p>This is a test page.</p>', $result);
    }

    public function testGenerateHtmlListWithLoop(): void
    {
        $template = '<ul>
@for(items as item)
    <li>{item.name} - ${item.price}</li>
@end
</ul>';

        $data = [
            'items' => [
                ['name' => 'Widget', 'price' => '29.99'],
                ['name' => 'Gadget', 'price' => '39.99'],
                ['name' => 'Doohickey', 'price' => '19.99'],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Widget - $29.99</li>', $result);
        $this->assertStringContainsString('<li>Gadget - $39.99</li>', $result);
        $this->assertStringContainsString('<li>Doohickey - $19.99</li>', $result);
        $this->assertStringContainsString('</ul>', $result);
    }

    public function testGenerateHtmlTableWithConditionals(): void
    {
        $template = '<table>
    <thead>
        <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
@for(products as product)
        <tr>
            <td>{product.name}</td>
            <td>${product.price}</td>
            <td>
@if(product.stock > 0)
                <span class="available">In Stock</span>
@else
                <span class="unavailable">Out of Stock</span>
@end
            </td>
        </tr>
@end
    </tbody>
</table>';

        $data = [
            'products' => [
                ['name' => 'Widget', 'price' => '29.99', 'stock' => 10],
                ['name' => 'Gadget', 'price' => '39.99', 'stock' => 0],
                ['name' => 'Doohickey', 'price' => '19.99', 'stock' => 5],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('<table>', $result);
        $this->assertStringContainsString('<th>Product</th>', $result);
        $this->assertStringContainsString('<td>Widget</td>', $result);
        $this->assertStringContainsString('<span class="available">In Stock</span>', $result);
        $this->assertStringContainsString('<span class="unavailable">Out of Stock</span>', $result);
    }

    public function testGenerateHtmlFormWithObjects(): void
    {
        $user = new class {
            public function getName(): string
            {
                return 'John Doe';
            }

            public function getEmail(): string
            {
                return 'john@example.com';
            }

            public function isAdmin(): bool
            {
                return true;
            }
        };

        $template = '<form method="post">
    <input type="text" name="name" value="{user.name}">
    <input type="email" name="email" value="{user.email}">
@if(user.admin)
    <input type="checkbox" name="admin" checked>
@else
    <input type="checkbox" name="admin">
@end
    <button type="submit">Save</button>
</form>';

        $result = $this->tsuku->process($template, ['user' => $user]);

        $this->assertStringContainsString('value="John Doe"', $result);
        $this->assertStringContainsString('value="john@example.com"', $result);
        $this->assertStringContainsString('checked>', $result);
    }

    public function testGenerateHtmlNavigationMenu(): void
    {
        $template = '<nav>
    <ul class="menu">
@for(menu as item)
        <li>
            <a href="{item.url}"@if(item.active) class="active"@end>{item.label}</a>
@if(item.children)
            <ul class="submenu">
@for(item.children as child)
                <li><a href="{child.url}">{child.label}</a></li>
@end
            </ul>
@end
        </li>
@end
    </ul>
</nav>';

        $data = [
            'menu' => [
                [
                    'label' => 'Home',
                    'url' => '/',
                    'active' => true,
                    'children' => null,
                ],
                [
                    'label' => 'Products',
                    'url' => '/products',
                    'active' => false,
                    'children' => [
                        ['label' => 'Widgets', 'url' => '/products/widgets'],
                        ['label' => 'Gadgets', 'url' => '/products/gadgets'],
                    ],
                ],
                [
                    'label' => 'About',
                    'url' => '/about',
                    'active' => false,
                    'children' => null,
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('<nav>', $result);
        $this->assertStringContainsString('<a href="/" class="active">Home</a>', $result);
        $this->assertStringContainsString('<a href="/products">Products</a>', $result);
        $this->assertStringContainsString('<ul class="submenu">', $result);
        $this->assertStringContainsString('<a href="/products/widgets">Widgets</a>', $result);
    }

    public function testGenerateHtmlCardGrid(): void
    {
        $template = '<div class="grid">
@for(cards as card)
    <div class="card">
        <h3>{card.title}</h3>
        <p>{card.description}</p>
@unless(card.disabled)
        <a href="{card.link}" class="btn">Learn More</a>
@end
    </div>
@end
</div>';

        $data = [
            'cards' => [
                [
                    'title' => 'Feature 1',
                    'description' => 'Amazing feature',
                    'link' => '/feature1',
                    'disabled' => false,
                ],
                [
                    'title' => 'Feature 2',
                    'description' => 'Coming soon',
                    'link' => '/feature2',
                    'disabled' => true,
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $this->assertStringContainsString('<div class="grid">', $result);
        $this->assertStringContainsString('<h3>Feature 1</h3>', $result);
        $this->assertStringContainsString('<a href="/feature1" class="btn">Learn More</a>', $result);
        $this->assertStringContainsString('<h3>Feature 2</h3>', $result);
        // Feature 2 should not have the button
        $this->assertEquals(1, substr_count($result, 'Learn More'));
    }
}
