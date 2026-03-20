<?php

namespace DiegoCopat\LaravelGeodata\Services;

use DiegoCopat\LaravelGeodata\Models\City;
use DiegoCopat\LaravelGeodata\Models\Country;
use DiegoCopat\LaravelGeodata\Models\Province;
use DiegoCopat\LaravelGeodata\Models\Region;
use Illuminate\Database\Eloquent\Collection;

class GeoDataService
{
    /**
     * Get all countries. By default returns only active ones.
     */
    public function countries(bool $includeHistorical = false): Collection
    {
        $query = Country::query()->orderBy('name');

        if (! $includeHistorical && ! config('geodata.include_historical_countries', false)) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Get all Italian regions.
     */
    public function regions(): Collection
    {
        return Region::query()->orderBy('name')->get();
    }

    /**
     * Get all Italian provinces.
     */
    public function provinces(): Collection
    {
        return Province::query()->orderBy('name')->get();
    }

    /**
     * Get provinces by region code.
     */
    public function provincesByRegion(string $regionCode): Collection
    {
        return Province::query()
            ->whereHas('region', fn ($q) => $q->where('code', $regionCode))
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all cities, optionally filtered by province code.
     */
    public function cities(?string $provinceCode = null): Collection
    {
        $query = City::query()->active()->orderBy('name');

        if ($provinceCode) {
            $query->byProvince($provinceCode);
        }

        return $query->get();
    }

    /**
     * Alias for cities() with province filter.
     */
    public function citiesByProvince(string $provinceCode): Collection
    {
        return $this->cities($provinceCode);
    }

    /**
     * Find a country or city by Belfiore code.
     * Returns Country for Z-codes, City otherwise.
     */
    public function findByBelfiore(string $code): Country|City|null
    {
        $code = strtoupper($code);

        if (str_starts_with($code, 'Z')) {
            return Country::byBelfiore($code)->first();
        }

        return City::byBelfiore($code)->first();
    }

    /**
     * Find cities by CAP (postal code).
     */
    public function findByCap(string $cap): Collection
    {
        return City::query()
            ->whereJsonContains('cap', $cap)
            ->orderBy('name')
            ->get();
    }

    /**
     * Search countries by name (partial match).
     */
    public function searchCountries(string $query): Collection
    {
        return Country::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Search cities by name (partial match).
     */
    public function searchCities(string $query, ?string $provinceCode = null): Collection
    {
        $builder = City::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->active()
            ->orderBy('name');

        if ($provinceCode) {
            $builder->byProvince($provinceCode);
        }

        return $builder->get();
    }

    /**
     * Search provinces by name or code.
     */
    public function searchProvinces(string $query): Collection
    {
        return Province::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('code', strtoupper($query));
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get Italy as a Country model.
     */
    public function italy(): ?Country
    {
        return Country::query()->where('iso_code', 'IT')->first();
    }

    /**
     * Check if a country identifier refers to Italy.
     */
    public function isItaly(string $identifier): bool
    {
        $identifier = strtolower(trim($identifier));

        return in_array($identifier, ['italia', 'italy', 'it', 'ita']);
    }
}
