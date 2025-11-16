<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

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
}
