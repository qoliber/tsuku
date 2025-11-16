<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\FormatterRegistry;
use Qoliber\Tsuku\Exception\FormatterNotFoundException;

class FormatterRegistryTest extends TestCase
{
    private FormatterRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new FormatterRegistry();
    }

    public function testRegisterAndGetFormatter(): void
    {
        $handler = fn($value) => number_format((float) $value, 2);

        $this->registry->register('currency', $handler);

        $this->assertTrue($this->registry->has('currency'));
        $this->assertSame($handler, $this->registry->get('currency'));
    }

    public function testHasReturnsFalseForNonExistentFormatter(): void
    {
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    public function testGetThrowsExceptionForNonExistentFormatter(): void
    {
        $this->expectException(FormatterNotFoundException::class);
        $this->expectExceptionMessage("Formatter 'nonexistent' not found");

        $this->registry->get('nonexistent');
    }
}
