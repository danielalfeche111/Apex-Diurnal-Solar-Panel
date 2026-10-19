<?php

declare(strict_types=1);

namespace App\Data;

final class PhilippineLocations
{
    private ?array $provinceCities = null;

    public function __construct(private readonly string $projectDir)
    {
    }

    public function provinceCities(): array
    {
        return $this->provinceCities ??= json_decode(
            (string) file_get_contents($this->projectDir.'/config/data/locations.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function provinces(): array
    {
        $provinces = array_keys($this->provinceCities());
        sort($provinces);

        return $provinces;
    }

    public function cities(): array
    {
        $all = [];
        foreach ($this->provinceCities() as $cities) {
            array_push($all, ...$cities);
        }
        $all = array_values(array_unique($all));
        sort($all);

        return $all;
    }

    public function cityProvince(): array
    {
        $map = [];
        foreach ($this->provinceCities() as $province => $cities) {
            foreach ($cities as $city) {
                $map[$city] = $province;
            }
        }

        return $map;
    }

    public function cityMatchesProvince(string $city, string $province): bool
    {
        $cities = $this->provinceCities()[$province] ?? [];
        if (in_array($city, $cities, true)) {
            return true;
        }
        $stripped = preg_replace('/\s+City$/i', '', $city);

        return in_array($stripped, $cities, true) || in_array($stripped.' City', $cities, true);
    }
}
