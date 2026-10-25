<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

class TestUser
{
    private string $name;
    private string $email;
    private bool $active;

    public function __construct(string $name, string $email, bool $active = true)
    {
        $this->name = $name;
        $this->email = $email;
        $this->active = $active;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
