<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

// Test class with various access patterns
class TestProduct
{
    public string $name = 'Widget';
    private string $sku = 'WDG-001';

    public function getPrice(): float
    {
        return 99.99;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function stock(): int
    {
        return 42;
    }
}
