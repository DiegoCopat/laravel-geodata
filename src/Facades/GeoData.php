<?php

namespace DiegoCopat\ItalianGeodata\Facades;

use DiegoCopat\ItalianGeodata\Models\City;
use DiegoCopat\ItalianGeodata\Models\Country;
use DiegoCopat\ItalianGeodata\Services\GeoDataService;
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
 * @see \DiegoCopat\ItalianGeodata\Services\GeoDataService
 */
class GeoData extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GeoDataService::class;
    }
}
