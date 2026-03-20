<?php

namespace DiegoCopat\LaravelGeodata\Facades;

use DiegoCopat\LaravelGeodata\Services\FiscalCodeService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string generate(array $data)
 * @method static bool validate(string $fiscalCode)
 * @method static array|null parse(string $fiscalCode)
 *
 * @see \DiegoCopat\LaravelGeodata\Services\FiscalCodeService
 */
class FiscalCode extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FiscalCodeService::class;
    }
}
