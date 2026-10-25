<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class ObjectAccessEdgeCasesTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testObjectsAsArrayInFunctions(): void
    {
        $template = 'Tags: @join(product.tags, ", ")';

        $data = [
            'product' => [
                'tags' => ['electronics', 'gadget', 'sale'],
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Tags: electronics, gadget, sale', $result);
    }

    public function testObjectMethodReturningArray(): void
    {
        $product = new class {
            /**
             * @return array<int, string>
             */
            public function getTags(): array
            {
                return ['new', 'featured', 'trending'];
            }
        };

        $template = 'Count: @length(product.tags)';
        $result = $this->tsuku->process($template, ['product' => $product]);
        $this->assertEquals('Count: 3', $result);
    }

    public function testDeeplyNestedObjectChain(): void
    {
        $template = 'Location: {user.profile.address.fullAddress}';

        $address = new Address('New York', 'USA');
        $profile = new Profile('Software Engineer', $address);

        $data = [
            'user' => [
                'profile' => $profile,
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Location: New York, USA', $result);
    }

    public function testNullSafetyInNestedAccess(): void
    {
        // When intermediate property is null, should return empty string
        $template = 'City: {user.profile.address.city}';

        $data = [
            'user' => [
                'profile' => null,
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('City: ', $result);
    }

    public function testObjectInComparison(): void
    {
        $product = new class {
            public function getPrice(): float
            {
                return 99.99;
            }
        };

        $template = '@if(product.price > 50)
Expensive
@else
Cheap
@end';

        $result = $this->tsuku->process($template, ['product' => $product]);
        $this->assertStringContainsString('Expensive', $result);
    }

    public function testObjectMethodInComparison(): void
    {
        $product = new class {
            public function getStock(): int
            {
                return 150;
            }
        };

        $template = '@if(product.stock >= 100)
High Stock
@else
Low Stock
@end';

        $result = $this->tsuku->process($template, ['product' => $product]);
        $this->assertStringContainsString('High Stock', $result);
    }

    public function testEmptyObjectArray(): void
    {
        $template = '@if(@length(items) > 0)
Has items
@else
No items
@end';

        $result = $this->tsuku->process($template, ['items' => []]);
        $this->assertStringContainsString('No items', $result);
    }

    public function testObjectWithNullGetter(): void
    {
        $product = new class {
            public function getName(): ?string
            {
                return null;
            }
        };

        $template = 'Name: [{product.name}]';
        $result = $this->tsuku->process($template, ['product' => $product]);
        $this->assertEquals('Name: []', $result);
    }

    public function testNestedLoopWithObjects(): void
    {
        $category1 = new class {
            public function getName(): string
            {
                return 'Electronics';
            }

            /**
             * @return array<int, mixed>
             */
            public function getProducts(): array
            {
                return [
                    ['name' => 'Phone'],
                    ['name' => 'Laptop'],
                ];
            }
        };

        $template = '@for(categories as category)
{category.name}:
@for(category.products as product)
  - {product.name}
@end
@end';

        $result = $this->tsuku->process($template, ['categories' => [$category1]]);
        $this->assertStringContainsString('Electronics:', $result);
        $this->assertStringContainsString('- Phone', $result);
        $this->assertStringContainsString('- Laptop', $result);
    }

    public function testObjectsInFunctionArguments(): void
    {
        $user = new class {
            public function getName(): string
            {
                return 'john doe';
            }
        };

        $template = 'Name: @upper(user.name)';
        $result = $this->tsuku->process($template, ['user' => $user]);
        $this->assertEquals('Name: JOHN DOE', $result);
    }

    public function testMultipleLevelsOfGetters(): void
    {
        $template = 'Address: {user.profile.address.city}';

        $address = new Address('London', 'UK');
        $profile = new Profile('Developer', $address);

        $data = [
            'user' => [
                'profile' => $profile,
            ],
        ];

        $result = $this->tsuku->process($template, $data);
        $this->assertEquals('Address: London', $result);
    }

    public function testBooleanGetterInConditional(): void
    {
        $user = new class {
            public function isAdmin(): bool
            {
                return true;
            }

            public function isPremium(): bool
            {
                return false;
            }
        };

        $template = '@if(user.admin)Admin@end @if(user.premium)Premium@end';
        $result = $this->tsuku->process($template, ['user' => $user]);
        $this->assertStringContainsString('Admin', $result);
        $this->assertStringNotContainsString('Premium', $result);
    }

    public function testObjectWithNoMatchingAccessor(): void
    {
        $obj = new class {
            public function someMethod(): string
            {
                return 'test';
            }
        };

        // Accessing non-existent property/method
        $template = 'Value: [{obj.nonExistent}]';
        $result = $this->tsuku->process($template, ['obj' => $obj]);
        $this->assertEquals('Value: []', $result);
    }

    public function testChainedObjectAccess(): void
    {
        $order = new class {
            public function getCustomer(): object
            {
                return new class {
                    public function getName(): string
                    {
                        return 'Jane Doe';
                    }

                    public function getEmail(): string
                    {
                        return 'jane@example.com';
                    }
                };
            }
        };

        $template = 'Customer: {order.customer.name} ({order.customer.email})';
        $result = $this->tsuku->process($template, ['order' => $order]);
        $this->assertEquals('Customer: Jane Doe (jane@example.com)', $result);
    }
}
