<?php

namespace DiegoCopat\LaravelGeodata\Facades;

use DiegoCopat\LaravelGeodata\Models\City;
use DiegoCopat\LaravelGeodata\Models\Country;
use DiegoCopat\LaravelGeodata\Services\GeoDataService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection countries(bool $includeHistorical = false)
 * @method static Collection regions()
 * @method static Collection provinces()
 * @method static Collection provincesByRegion(string $regionCode)
 * @method static Collection cities(?string $provinceCode = null)
 * @method static Collection citiesByProvince(string $provinceCode)
 * @method static Country|City|null findByBelfiore(string $code)
 * @method static Collection findByCap(string $cap)
 * @method static Collection searchCountries(string $query)
 * @method static Collection searchCities(string $query, ?string $provinceCode = null)
 * @method static Collection searchProvinces(string $query)
 * @method static Country|null italy()
 * @method static bool isItaly(string $identifier)
 *
 * @see \DiegoCopat\LaravelGeodata\Services\GeoDataService
 */
class GeoData extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeoDataService::class;
    }
}
