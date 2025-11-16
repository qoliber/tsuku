<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

// Test class with magic methods
class MagicObject
{
    private array $data = ['magic' => 'works', 'count' => 42];

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'getDynamic') {
            return 'dynamic result';
        }
        return null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }
}

// Test class implementing ArrayAccess
class ArrayAccessObject implements \ArrayAccess
{
    private array $data = ['key1' => 'value1', 'key2' => 'value2'];

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}

// Test class that returns objects
class Profile
{
    public function __construct(
        private string $bio,
        private Address $address
    ) {
    }

    public function getBio(): string
    {
        return $this->bio;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }
}

class Address
{
    public function __construct(
        private string $city,
        private string $country
    ) {
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getFullAddress(): string
    {
        return "{$this->city}, {$this->country}";
    }
}

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
