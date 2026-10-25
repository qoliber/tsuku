<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\DirectiveRegistry;
use Qoliber\Tsuku\Exception\DirectiveNotFoundException;

class DirectiveRegistryTest extends TestCase
{
    private DirectiveRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new DirectiveRegistry();
    }

    public function testRegisterAndGetDirective(): void
    {
        $handler = fn($value) => strtoupper($value);

        $this->registry->register('uppercase', $handler);

        $this->assertTrue($this->registry->has('uppercase'));
        $this->assertSame($handler, $this->registry->get('uppercase'));
    }

    public function testHasReturnsFalseForNonExistentDirective(): void
    {
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function testGetThrowsExceptionForNonExistentDirective(): void
    {
        $this->expectException(DirectiveNotFoundException::class);
        $this->expectExceptionMessage("Directive 'nonexistent' not found");

        $this->registry->get('nonexistent');
    }

    public function testNamesListsRegisteredDirectives(): void
    {
        $this->assertSame([], $this->registry->names());

        $this->registry->register('cdata', fn(string $body): string => $body);
        $this->registry->register('_x1', fn(string $body): string => $body);

        $this->assertSame(['cdata', '_x1'], $this->registry->names());
    }

    public function testRegisteringAgainReplacesTheHandler(): void
    {
        $first = fn(string $body): string => 'first';
        $second = fn(string $body): string => 'second';
        $this->registry->register('x', $first);
        $this->registry->register('x', $second);

        $this->assertSame($second, $this->registry->get('x'));
        $this->assertSame(['x'], $this->registry->names());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedNames(): array
    {
        $names = DirectiveRegistry::RESERVED;

        return array_combine($names, array_map(fn(string $name): array => [$name], $names));
    }

    #[DataProvider('reservedNames')]
    public function testReservedNamesAreRejected(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'{$name}' is a built-in directive and cannot be registered");

        $this->registry->register($name, fn(string $body): string => $body);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'dash' => ['my-directive'],
            'leading digit' => ['1x'],
            'at sign' => ['@x'],
            'space' => ['a b'],
            'trailing newline' => ["x\n"],
        ];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNamesAreRejected(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid directive name '{$name}'");

        $this->registry->register($name, fn(string $body): string => $body);
    }

    public function testNamesSimilarToBuiltInsAreAllowed(): void
    {
        foreach (['iff', 'ending', 'elsewhere', 'defaults', 'For', 'cases'] as $name) {
            $this->registry->register($name, fn(string $body): string => $body);
        }

        $this->assertCount(6, $this->registry->names());
    }
}
