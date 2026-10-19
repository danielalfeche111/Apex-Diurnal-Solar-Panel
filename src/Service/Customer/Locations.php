<?php

declare(strict_types=1);

namespace App\Service\Customer;

use App\Data\PhilippineLocations;

final class Locations
{
    public function __construct(private readonly PhilippineLocations $locations)
    {
    }

    public function map(): array
    {
        return $this->locations->provinceCities();
    }

    public function provinces(): array
    {
        return $this->locations->provinces();
    }

    public function cities(): array
    {
        return $this->locations->cities();
    }

    public function cityMatchesProvince(string $city, string $province): bool
    {
        return $this->locations->cityMatchesProvince($city, $province);
    }
}
