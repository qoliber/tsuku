<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

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
