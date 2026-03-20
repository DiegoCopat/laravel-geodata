<?php

namespace DiegoCopat\LaravelGeodata\Tests;

use DiegoCopat\LaravelGeodata\Models\City;
use DiegoCopat\LaravelGeodata\Models\Country;
use DiegoCopat\LaravelGeodata\Models\Province;
use DiegoCopat\LaravelGeodata\Models\Region;
use DiegoCopat\LaravelGeodata\Services\FiscalCodeService;

class FiscalCodeServiceTest extends TestCase
{
    private FiscalCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FiscalCodeService::class);
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
            'is_capital' => true,
            'is_active' => true,
        ]);

        $regionLazio = Region::create([
            'country_id' => $italy->id,
            'name' => 'Lazio',
            'slug' => 'lazio',
            'code' => '12',
        ]);

        $provinceRoma = Province::create([
            'region_id' => $regionLazio->id,
            'name' => 'Roma',
            'slug' => 'roma',
            'code' => 'RM',
        ]);

        City::create([
            'province_id' => $provinceRoma->id,
            'name' => 'Roma',
            'slug' => 'roma',
            'belfiore_code' => 'H501',
            'istat_code' => '058091',
            'cap' => ['00118', '00119', '00120'],
            'is_capital' => true,
            'is_active' => true,
        ]);
    }

    public function test_generate_fiscal_code_with_belfiore(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'gender' => 'M',
            'birth_date' => '1990-01-15',
            'belfiore_code' => 'L378',
        ]);

        $this->assertEquals(16, strlen($cf));
        $this->assertStringStartsWith('RSS', $cf); // Rossi → RSS
        $this->assertEquals('MRA', substr($cf, 3, 3)); // Mario → MRA
        $this->assertStringContains('L378', $cf);
    }

    public function test_generate_fiscal_code_with_city_lookup(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'gender' => 'M',
            'birth_date' => '1990-01-15',
            'birth_city' => 'Trento',
            'birth_province' => 'TN',
        ]);

        $this->assertEquals(16, strlen($cf));
        $this->assertStringContains('L378', $cf);
    }

    public function test_generate_fiscal_code_female(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Maria',
            'lastname' => 'Bianchi',
            'gender' => 'F',
            'birth_date' => '1985-06-22',
            'belfiore_code' => 'H501',
        ]);

        $this->assertEquals(16, strlen($cf));
        // Day should be 22 + 40 = 62
        $this->assertEquals('62', substr($cf, 9, 2));
    }

    public function test_generate_foreign_birth(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Ion',
            'lastname' => 'Popescu',
            'gender' => 'M',
            'birth_date' => '1988-03-10',
            'birth_country' => 'Romania',
        ]);

        $this->assertEquals(16, strlen($cf));
        $this->assertStringContains('Z129', $cf);
    }

    public function test_validate_correct_fiscal_code(): void
    {
        // Generate and then validate
        $cf = $this->service->generate([
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'gender' => 'M',
            'birth_date' => '1990-01-15',
            'belfiore_code' => 'L378',
        ]);

        $this->assertTrue($this->service->validate($cf));
    }

    public function test_validate_invalid_length(): void
    {
        $this->assertFalse($this->service->validate('ABC'));
        $this->assertFalse($this->service->validate(''));
    }

    public function test_validate_wrong_check_character(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'gender' => 'M',
            'birth_date' => '1990-01-15',
            'belfiore_code' => 'L378',
        ]);

        // Alter the check character
        $wrongCf = substr($cf, 0, 15) . ($cf[15] === 'A' ? 'B' : 'A');
        $this->assertFalse($this->service->validate($wrongCf));
    }

    public function test_parse_fiscal_code(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'gender' => 'M',
            'birth_date' => '1990-01-15',
            'belfiore_code' => 'L378',
        ]);

        $parsed = $this->service->parse($cf);

        $this->assertNotNull($parsed);
        $this->assertEquals('M', $parsed['gender']);
        $this->assertEquals(1, $parsed['birth_month']);
        $this->assertEquals(15, $parsed['birth_day']);
        $this->assertEquals('1990', $parsed['birth_year']);
        $this->assertEquals('L378', $parsed['belfiore_code']);
        $this->assertFalse($parsed['is_foreign']);
        $this->assertEquals('Trento', $parsed['birth_place']);
    }

    public function test_parse_female_fiscal_code(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Maria',
            'lastname' => 'Bianchi',
            'gender' => 'F',
            'birth_date' => '1985-06-22',
            'belfiore_code' => 'H501',
        ]);

        $parsed = $this->service->parse($cf);

        $this->assertNotNull($parsed);
        $this->assertEquals('F', $parsed['gender']);
        $this->assertEquals(22, $parsed['birth_day']);
        $this->assertEquals(6, $parsed['birth_month']);
    }

    public function test_parse_foreign_birth(): void
    {
        $cf = $this->service->generate([
            'firstname' => 'Ion',
            'lastname' => 'Popescu',
            'gender' => 'M',
            'birth_date' => '1988-03-10',
            'belfiore_code' => 'Z129',
        ]);

        $parsed = $this->service->parse($cf);

        $this->assertNotNull($parsed);
        $this->assertTrue($parsed['is_foreign']);
        $this->assertEquals('Romania', $parsed['birth_place']);
    }

    public function test_parse_returns_null_for_invalid(): void
    {
        $this->assertNull($this->service->parse('ABC'));
        $this->assertNull($this->service->parse(''));
    }

    public function test_surname_encoding_rules(): void
    {
        // "Rossi" → consonants R,S,S → RSS
        $cf = $this->service->generate([
            'firstname' => 'A',
            'lastname' => 'Rossi',
            'gender' => 'M',
            'birth_date' => '2000-01-01',
            'belfiore_code' => 'L378',
        ]);
        $this->assertStringStartsWith('RSS', $cf);

        // "Fo" → consonants F, vowels O → pad with X → FOX
        $cf2 = $this->service->generate([
            'firstname' => 'A',
            'lastname' => 'Fo',
            'gender' => 'M',
            'birth_date' => '2000-01-01',
            'belfiore_code' => 'L378',
        ]);
        $this->assertStringStartsWith('FOX', $cf2);
    }

    public function test_firstname_encoding_four_consonants_rule(): void
    {
        // "Francesco" → consonants F,R,N,C,S,C → 4+ → take 1st,3rd,4th → F,N,C → FNC
        $cf = $this->service->generate([
            'firstname' => 'Francesco',
            'lastname' => 'Rossi',
            'gender' => 'M',
            'birth_date' => '2000-01-01',
            'belfiore_code' => 'L378',
        ]);
        $this->assertEquals('FNC', substr($cf, 3, 3));
    }

    /**
     * Helper to check if string contains substring.
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
