# Italian Geodata

Complete Italian geographic data + world countries + fiscal code (Codice Fiscale) generation/validation for Laravel.

## Features

- **295 countries** with Belfiore codes (includes historical entities for fiscal code retroactive calculation)
- **20 Italian regions**, **107 provinces**, **~7,900 municipalities** with CAP, coordinates, Belfiore codes
- **Codice Fiscale**: generate, validate, and parse Italian fiscal codes
- Artisan commands for seeding, updating, and statistics
- Configurable table names to avoid conflicts
- Laravel 10/11/12/13 compatible

## Installation

```bash
composer require diegocopat/italian-geodata
```

The package auto-discovers the service provider. Publish the config (optional):

```bash
php artisan vendor:publish --tag=italian-geodata-config
```

Run migrations and seed data:

```bash
php artisan migrate
php artisan geodata:seed
```

## Configuration

```php
// config/italian-geodata.php

return [
    'tables' => [
        'countries' => 'countries',
        'regions' => 'regions',
        'provinces' => 'provinces',
        'cities' => 'cities',
    ],
    'include_historical_countries' => false,
];
```

## Usage

### GeoData Facade

```php
use DiegoCopat\ItalianGeodata\Facades\GeoData;

// Countries
GeoData::countries();                          // active countries
GeoData::countries(includeHistorical: true);   // all 295 including historical
GeoData::searchCountries('Roman');             // search by name
GeoData::italy();                              // get Italy

// Regions & Provinces
GeoData::regions();
GeoData::provinces();
GeoData::provincesByRegion('04');              // by region code

// Cities
GeoData::cities();                             // all active cities
GeoData::citiesByProvince('TN');               // by province code
GeoData::searchCities('Trento');               // search by name
GeoData::searchCities('Rover', 'TN');          // search within province

// Lookups
GeoData::findByBelfiore('L378');               // city: Trento
GeoData::findByBelfiore('Z129');               // country: Romania
GeoData::findByCap('38122');                   // cities by postal code

// Utilities
GeoData::isItaly('IT');                        // true
```

### FiscalCode Facade

```php
use DiegoCopat\ItalianGeodata\Facades\FiscalCode;

// Generate
$cf = FiscalCode::generate([
    'firstname' => 'Mario',
    'lastname' => 'Rossi',
    'gender' => 'M',
    'birth_date' => '1990-01-15',
    'birth_city' => 'Trento',
    'birth_province' => 'TN',
]);
// Returns: RSSMRA90A15L378X

// Generate with direct Belfiore code
$cf = FiscalCode::generate([
    'firstname' => 'Mario',
    'lastname' => 'Rossi',
    'gender' => 'M',
    'birth_date' => '1990-01-15',
    'belfiore_code' => 'L378',
]);

// Generate for foreign-born
$cf = FiscalCode::generate([
    'firstname' => 'Ion',
    'lastname' => 'Popescu',
    'gender' => 'M',
    'birth_date' => '1988-03-10',
    'birth_country' => 'Romania',
]);

// Validate
FiscalCode::validate('RSSMRA90A15L378X'); // true or false

// Parse
$data = FiscalCode::parse('RSSMRA90A15L378X');
// Returns:
// [
//     'surname_code' => 'RSS',
//     'firstname_code' => 'MRA',
//     'birth_year' => '1990',
//     'birth_month' => 1,
//     'birth_day' => 15,
//     'gender' => 'M',
//     'belfiore_code' => 'L378',
//     'birth_date' => '1990-01-15',
//     'birth_place' => 'Trento',
//     'is_foreign' => false,
//     'omocodia_level' => 0,
// ]
```

### Eloquent Models

All models are available for direct queries:

```php
use DiegoCopat\ItalianGeodata\Models\{Country, Region, Province, City};

// Scopes
Country::active()->get();
City::byProvince('TN')->get();
City::byBelfiore('L378')->first();
City::capitals()->get();

// Relationships
$city = City::find(1);
$city->province;
$city->province->region;
$city->province->region->country;

$province = Province::byCode('TN')->first();
$province->cities;
$province->region;
```

## Artisan Commands

### Seeding (country-scoped)

```bash
# Seed world countries + all available detail data (currently: Italy)
php artisan geodata:seed

# Seed world countries + only Italy detail (regions, provinces, cities)
php artisan geodata:seed --country=it

# Later: add Germany detail without touching Italy
php artisan geodata:seed --country=de

# Multiple countries at once
php artisan geodata:seed --country=it --country=de --country=fr

# Fresh reload — country-scoped (only clears Italy's regions/provinces/cities)
php artisan geodata:seed --country=it --fresh

# Fresh reload everything
php artisan geodata:seed --fresh

# Seed only countries (skip detail data)
php artisan geodata:seed --no-countries --country=it

# List available country data packages
php artisan geodata:seed --list
```

### Data management

```bash
# Update data files from remote source
php artisan geodata:update

# Preview changes without downloading
php artisan geodata:update --dry-run

# Show statistics
php artisan geodata:stats
```

### Adding a new country

To add geographic data for a new country, create a directory under `database/data/countries/`:

```
database/data/countries/de/
├── regions.json       # German Bundesländer
├── provinces.json     # Kreise/Landkreise
├── cities.json        # Gemeinden
└── cap.json           # Postleitzahlen (optional)
```

Then run `php artisan geodata:seed --country=de`. See the `it/` directory for the expected JSON format.

## Data Sources

- [DarioCorno/database_comuni_italiani](https://github.com/DarioCorno/database_comuni_italiani) — ISTAT-based Italian geographic data
- [Garda Informatica](https://www.gardainformatica.it/database-comuni-italiani) — Reference

## License

MIT License. See [LICENSE](LICENSE) for details.
