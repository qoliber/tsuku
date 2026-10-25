<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

// Test class with magic methods (__get, __call, __isset)
class MagicObject
{
    /** @var array<string, mixed> */
    private array $data = ['magic' => 'works', 'count' => 42];

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @param array<int, mixed> $arguments
     */
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
