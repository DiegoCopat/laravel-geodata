<?php

namespace DiegoCopat\ItalianGeodata\Tests;

use DiegoCopat\ItalianGeodata\Models\City;
use DiegoCopat\ItalianGeodata\Models\Country;
use DiegoCopat\ItalianGeodata\Models\Province;
use DiegoCopat\ItalianGeodata\Models\Region;
use DiegoCopat\ItalianGeodata\Services\GeoDataService;

class GeoDataServiceTest extends TestCase
{
    private GeoDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GeoDataService::class);
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $italy = Country::create([
            'name' => 'Italia',
            'slug' => 'italia',
            'iso_code' => 'IT',
            'belfiore_code' => 'Z000',
            'is_active' => true,
        ]);

        Country::create([
            'name' => 'Romania',
            'slug' => 'romania',
            'iso_code' => 'RO',
            'belfiore_code' => 'Z129',
            'is_active' => true,
        ]);

        Country::create([
            'name' => 'Unione Repubbliche Socialiste Sovietiche',
            'slug' => 'urss',
            'iso_code' => 'SU',
            'belfiore_code' => 'Z135',
            'is_active' => false,
        ]);

        $region = Region::create([
            'country_id' => $italy->id,
            'name' => 'Trentino-Alto Adige',
            'slug' => 'trentino-alto-adige',
            'code' => '04',
        ]);

        $province = Province::create([
            'region_id' => $region->id,
            'name' => 'Trento',
            'slug' => 'trento',
            'code' => 'TN',
        ]);

        City::create([
            'province_id' => $province->id,
            'name' => 'Trento',
            'slug' => 'trento',
            'belfiore_code' => 'L378',
            'istat_code' => '022205',
            'cap' => ['38121', '38122', '38123'],
            'lat' => 46.0664228,
            'lon' => 11.1257601,
            'is_capital' => true,
            'is_active' => true,
        ]);

        City::create([
            'province_id' => $province->id,
            'name' => 'Rovereto',
            'slug' => 'rovereto',
            'belfiore_code' => 'H612',
            'istat_code' => '022156',
            'cap' => ['38068'],
            'is_capital' => false,
            'is_active' => true,
        ]);
    }

    public function test_countries_returns_only_active_by_default(): void
    {
        $countries = $this->service->countries();

        $this->assertCount(2, $countries); // Italia + Romania, not URSS
        $this->assertFalse($countries->contains('name', 'Unione Repubbliche Socialiste Sovietiche'));
    }

    public function test_countries_includes_historical_when_requested(): void
    {
        $countries = $this->service->countries(includeHistorical: true);

        $this->assertCount(3, $countries);
        $this->assertTrue($countries->contains('name', 'Unione Repubbliche Socialiste Sovietiche'));
    }

    public function test_regions(): void
    {
        $regions = $this->service->regions();

        $this->assertCount(1, $regions);
        $this->assertEquals('Trentino-Alto Adige', $regions->first()->name);
    }

    public function test_provinces(): void
    {
        $provinces = $this->service->provinces();

        $this->assertCount(1, $provinces);
        $this->assertEquals('TN', $provinces->first()->code);
    }

    public function test_cities_by_province(): void
    {
        $cities = $this->service->citiesByProvince('TN');

        $this->assertCount(2, $cities);
    }

    public function test_find_by_belfiore_city(): void
    {
        $result = $this->service->findByBelfiore('L378');

        $this->assertInstanceOf(City::class, $result);
        $this->assertEquals('Trento', $result->name);
    }

    public function test_find_by_belfiore_country(): void
    {
        $result = $this->service->findByBelfiore('Z129');

        $this->assertInstanceOf(Country::class, $result);
        $this->assertEquals('Romania', $result->name);
    }

    public function test_find_by_belfiore_historical_country(): void
    {
        $result = $this->service->findByBelfiore('Z135');

        $this->assertInstanceOf(Country::class, $result);
        $this->assertEquals('Unione Repubbliche Socialiste Sovietiche', $result->name);
    }

    public function test_find_by_belfiore_not_found(): void
    {
        $this->assertNull($this->service->findByBelfiore('ZZZZ'));
    }

    public function test_find_by_cap(): void
    {
        $cities = $this->service->findByCap('38122');

        $this->assertCount(1, $cities);
        $this->assertEquals('Trento', $cities->first()->name);
    }

    public function test_search_cities(): void
    {
        $results = $this->service->searchCities('Tren');

        $this->assertCount(1, $results);
        $this->assertEquals('Trento', $results->first()->name);
    }

    public function test_search_countries(): void
    {
        $results = $this->service->searchCountries('Roman');

        $this->assertCount(1, $results);
        $this->assertEquals('Romania', $results->first()->name);
    }

    public function test_italy(): void
    {
        $italy = $this->service->italy();

        $this->assertNotNull($italy);
        $this->assertEquals('Italia', $italy->name);
        $this->assertEquals('IT', $italy->iso_code);
    }

    public function test_is_italy(): void
    {
        $this->assertTrue($this->service->isItaly('italia'));
        $this->assertTrue($this->service->isItaly('IT'));
        $this->assertTrue($this->service->isItaly('Italy'));
        $this->assertFalse($this->service->isItaly('Romania'));
    }
}
