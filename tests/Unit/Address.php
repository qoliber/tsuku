<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

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
