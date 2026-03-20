<?php

namespace DiegoCopat\LaravelGeodata\Commands;

use DiegoCopat\LaravelGeodata\Models\City;
use DiegoCopat\LaravelGeodata\Models\Country;
use DiegoCopat\LaravelGeodata\Models\Province;
use DiegoCopat\LaravelGeodata\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedCommand extends Command
{
    protected $signature = 'geodata:seed
        {--country=* : Country ISO codes to seed detail data for (e.g. --country=it --country=de)}
        {--fresh : Truncate tables before seeding (BLOCKED if external tables reference geodata)}
        {--force : Force --fresh even with warnings (development only!)}
        {--no-countries : Skip seeding the countries table}
        {--skip-wizard : Skip interactive prompts, use defaults}
        {--list : List available country data packages}';

    protected $description = 'Seed geographic data: world countries + detailed regions/provinces/cities per country';

    private const HISTORICAL_ISO_CODES = [
        'SU', 'YU', 'CS', 'DDR', 'VNN', 'VNS', 'YS', 'CB', 'NHE', 'SCG',
    ];

    private const COUNTRY_LABELS = [
        'it' => 'Italia (regioni, province, ~7.900 comuni con CAP)',
        'de' => 'Germania (Bundeslaender, Kreise, Gemeinden)',
        'fr' => 'Francia (regions, departements, communes)',
        'es' => 'Spagna (comunidades, provincias, municipios)',
        'at' => 'Austria (Bundeslaender, Bezirke, Gemeinden)',
        'ch' => 'Svizzera (cantoni, distretti, comuni)',
    ];

    public function handle(): int
    {
        $dataPath = $this->getDataPath();

        if ($this->option('list')) {
            return $this->listAvailableCountries($dataPath);
        }

        $requestedCountries = $this->option('country');
        $availableCountries = $this->getAvailableCountries($dataPath);
        $isFirstRun = $this->isFirstRun();

        // Determine which countries to seed
        if (! empty($requestedCountries)) {
            $countriesToSeed = array_map('strtolower', $requestedCountries);
            $invalid = array_diff($countriesToSeed, $availableCountries);
            if (! empty($invalid)) {
                $this->error('No data available for: ' . implode(', ', array_map('strtoupper', $invalid)));
                $this->info('Available: ' . implode(', ', array_map('strtoupper', $availableCountries)));
                return self::FAILURE;
            }
        } elseif ($isFirstRun && ! $this->option('skip-wizard')) {
            $countriesToSeed = $this->firstRunWizard($availableCountries);
            if ($countriesToSeed === null) {
                $this->info('Seeding cancelled.');
                return self::SUCCESS;
            }
        } else {
            $countriesToSeed = $availableCountries;
        }

        // Handle --fresh with safety checks
        if ($this->option('fresh')) {
            $freshResult = $this->handleFresh($countriesToSeed);
            if ($freshResult === false) {
                return self::FAILURE;
            }
        }

        $this->showSeedingPlan($countriesToSeed);

        // 1. Seed world countries (always upsert, never delete)
        if (! $this->option('no-countries')) {
            $this->seedCountries($dataPath);
        }

        // 2. Seed + sync detail data per country
        foreach ($countriesToSeed as $countryCode) {
            $this->seedCountryDetail($dataPath, $countryCode);
        }

        $this->newLine();
        $this->info('Seeding completed successfully!');

        if ($isFirstRun) {
            $this->newLine();
            $this->components->info('Next steps:');
            $this->line('  - Run <comment>php artisan geodata:stats</comment> to verify loaded data');
            $this->line('  - Add more countries later with <comment>php artisan geodata:seed --country=XX</comment>');
            $this->line('  - Update data files with <comment>php artisan geodata:update</comment>');
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────
    //  FRESH: safety checks
    // ──────────────────────────────────────────────

    /**
     * Handle --fresh with safety checks.
     * Returns false if blocked, true if ok to proceed.
     */
    private function handleFresh(array $countriesToSeed): bool
    {
        // Check for external FK references to our tables
        $externalRefs = $this->findExternalForeignKeys();

        if (! empty($externalRefs)) {
            $this->newLine();
            $this->error('BLOCKED: --fresh cannot run because other tables reference geodata tables.');
            $this->newLine();
            $this->warn('External references found:');

            foreach ($externalRefs as $ref) {
                $this->line("  - <comment>{$ref['table']}.{$ref['column']}</comment> → {$ref['referenced_table']}");
            }

            $this->newLine();
            $this->info('Why this is blocked:');
            $this->line('  Truncating would leave orphan records in your tables (e.g. addresses');
            $this->line('  pointing to deleted cities). This would corrupt your data.');
            $this->newLine();
            $this->info('What to do instead:');
            $this->line('  Run <comment>php artisan geodata:seed</comment> without --fresh.');
            $this->line('  The sync mode will safely: update existing records, add new ones,');
            $this->line('  and mark removed entries as is_active=false (never delete).');

            if ($this->option('force')) {
                $this->newLine();
                $this->warn('--force detected. Proceeding anyway — THIS MAY CORRUPT DATA!');
                $this->doFresh($countriesToSeed);
                return true;
            }

            return false;
        }

        // No external references — safe to truncate
        $this->doFresh($countriesToSeed);
        return true;
    }

    /**
     * Find external tables that have FK references to our geodata tables.
     */
    private function findExternalForeignKeys(): array
    {
        $driver = DB::getDriverName();
        $geodataTables = [
            config('geodata.tables.countries', 'countries'),
            config('geodata.tables.regions', 'regions'),
            config('geodata.tables.provinces', 'provinces'),
            config('geodata.tables.cities', 'cities'),
        ];

        $refs = [];

        if ($driver === 'mysql') {
            $placeholders = implode(',', array_fill(0, count($geodataTables), '?'));

            $results = DB::select("
                SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME IN ({$placeholders})
                AND TABLE_NAME NOT IN ({$placeholders})
            ", array_merge($geodataTables, $geodataTables));

            foreach ($results as $row) {
                $refs[] = [
                    'table' => $row->TABLE_NAME,
                    'column' => $row->COLUMN_NAME,
                    'referenced_table' => $row->REFERENCED_TABLE_NAME,
                ];
            }
        } elseif ($driver === 'sqlite') {
            // SQLite: check pragma foreign_key_list for each table
            $allTables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table'"))
                ->pluck('name')
                ->diff($geodataTables)
                ->values();

            foreach ($allTables as $table) {
                try {
                    $fks = DB::select("PRAGMA foreign_key_list({$table})");
                    foreach ($fks as $fk) {
                        if (in_array($fk->table, $geodataTables)) {
                            $refs[] = [
                                'table' => $table,
                                'column' => $fk->from,
                                'referenced_table' => $fk->table,
                            ];
                        }
                    }
                } catch (\Exception) {
                    continue;
                }
            }
        }

        return $refs;
    }

    /**
     * Perform the actual fresh truncation.
     */
    /**
     * Perform fresh delete — in correct order (cities → provinces → regions)
     * so internal FKs are respected. If external FKs exist, the DB will
     * naturally block the delete (no FK_CHECKS bypass).
     */
    private function doFresh(array $countriesToSeed): void
    {
        try {
            if (empty($countriesToSeed)) {
                $counts = [
                    'cities' => City::count(),
                    'provinces' => Province::count(),
                    'regions' => Region::count(),
                ];
                // Delete in correct order: children first
                City::query()->delete();
                Province::query()->delete();
                Region::query()->delete();
                $this->warn("  Cleared ALL: {$counts['regions']} regions, {$counts['provinces']} provinces, {$counts['cities']} cities");
            } else {
                foreach ($countriesToSeed as $countryCode) {
                    $isoCode = strtoupper($countryCode);
                    $country = Country::where('iso_code', $isoCode)->first();
                    if (! $country) {
                        continue;
                    }

                    $regionIds = Region::where('country_id', $country->id)->pluck('id');
                    $provinceIds = Province::whereIn('region_id', $regionIds)->pluck('id');
                    $counts = [
                        'cities' => City::whereIn('province_id', $provinceIds)->count(),
                        'provinces' => $provinceIds->count(),
                        'regions' => $regionIds->count(),
                    ];

                    // Delete in correct order: children first
                    City::whereIn('province_id', $provinceIds)->delete();
                    Province::whereIn('region_id', $regionIds)->delete();
                    Region::where('country_id', $country->id)->delete();

                    $this->warn("  Cleared {$isoCode}: {$counts['regions']} regions, {$counts['provinces']} provinces, {$counts['cities']} cities");
                }
            }
        } catch (\Illuminate\Database\QueryException $e) {
            $this->newLine();
            $this->error('Cannot delete: a foreign key constraint prevented the operation.');
            $this->line('  This means other tables in your application reference geodata records.');
            $this->line('  Use <comment>php artisan geodata:seed</comment> without --fresh (sync mode) instead.');
            $this->newLine();
            throw $e;
        }

        $this->newLine();
    }

    // ──────────────────────────────────────────────
    //  WIZARD: first run interactive setup
    // ──────────────────────────────────────────────

    private function firstRunWizard(array $availableCountries): ?array
    {
        $this->newLine();
        $this->components->info('Italian Geodata - First Run Setup');
        $this->newLine();
        $this->line('  Welcome! This package provides geographic data for your Laravel application.');
        $this->line('  On first install, you can choose what data to load.');
        $this->newLine();
        $this->line('  <comment>What will be loaded:</comment>');
        $this->line('  - <info>295 world countries</info> (always loaded, needed for fiscal code calculation)');
        $this->line('  - <info>Detail data</info> (regions, provinces, cities) for the countries you choose');
        $this->newLine();

        $choices = [
            'default' => 'Default: all countries + Italy detail (recommended)',
            'choose'  => 'Choose which countries to load in detail',
            'all'     => 'Load all available detail data (' . implode(', ', array_map('strtoupper', $availableCountries)) . ')',
            'none'    => 'Only world countries (no detail data)',
            'cancel'  => 'Cancel',
        ];

        $answer = $this->choice('How would you like to set up your geodata?', array_values($choices), 0);
        $selected = array_search($answer, $choices);

        return match ($selected) {
            'default' => ['it'],
            'all'     => $availableCountries,
            'none'    => [],
            'cancel'  => null,
            'choose'  => $this->chooseCountries($availableCountries),
        };
    }

    private function chooseCountries(array $availableCountries): array
    {
        $options = [];
        foreach ($availableCountries as $code) {
            $label = self::COUNTRY_LABELS[$code] ?? strtoupper($code);
            $options[$code] = strtoupper($code) . ' - ' . $label;
        }

        $selected = $this->choice(
            'Select countries (comma-separated numbers for multiple)',
            array_values($options),
            0,
            null,
            true
        );

        $result = [];
        foreach ($selected as $label) {
            foreach ($options as $code => $optionLabel) {
                if ($optionLabel === $label) {
                    $result[] = $code;
                    break;
                }
            }
        }

        return $result;
    }

    // ──────────────────────────────────────────────
    //  SEED: upsert + deactivation sync
    // ──────────────────────────────────────────────

    private function seedCountries(string $dataPath): void
    {
        $this->components->task('Seeding world countries', function () use ($dataPath) {
            $nations = $this->loadJson($dataPath . '/gi_nazioni.json');
            $created = 0;
            $updated = 0;

            foreach ($nations as $nation) {
                $name = $this->cleanName($nation['denominazione_nazione'] ?? '');
                $sigla = trim($nation['sigla_nazione'] ?? '');
                $belfiore = trim($nation['codice_belfiore'] ?? '');
                $citizenship = $this->cleanName($nation['denominazione_cittadinanza'] ?? '');

                if (empty($belfiore) || empty($name)) {
                    continue;
                }

                $existing = Country::where('belfiore_code', $belfiore)->first();

                if ($existing) {
                    $existing->update([
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'iso_code' => $sigla ?: null,
                        'citizenship' => $citizenship ?: null,
                        'is_active' => $this->isCountryActive($sigla),
                    ]);
                    $updated++;
                } else {
                    Country::create([
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'iso_code' => $sigla ?: null,
                        'belfiore_code' => $belfiore,
                        'citizenship' => $citizenship ?: null,
                        'is_active' => $this->isCountryActive($sigla),
                    ]);
                    $created++;
                }
            }

            $parts = [];
            if ($created > 0) $parts[] = "{$created} created";
            if ($updated > 0) $parts[] = "{$updated} updated";
            return implode(', ', $parts) ?: 'no changes';
        });
    }

    private function seedCountryDetail(string $dataPath, string $countryCode): void
    {
        $countryPath = $dataPath . '/countries/' . strtolower($countryCode);
        $isoCode = strtoupper($countryCode);

        $this->newLine();
        $this->info("Seeding {$isoCode} detail data...");

        $country = Country::where('iso_code', $isoCode)->first();
        if (! $country) {
            $this->error("Country {$isoCode} not found in countries table. Seed countries first.");
            return;
        }

        if (file_exists($countryPath . '/regions.json')) {
            $this->seedRegions($countryPath, $country);
        }

        if (file_exists($countryPath . '/provinces.json')) {
            $this->seedProvinces($countryPath, $country);
        }

        if (file_exists($countryPath . '/cities.json')) {
            $hasCap = file_exists($countryPath . '/cap.json');
            $this->seedCities($countryPath, $country, $hasCap);
        }
    }

    private function seedRegions(string $countryPath, Country $country): void
    {
        $this->components->task("  Seeding regions", function () use ($countryPath, $country) {
            $regions = $this->loadJson($countryPath . '/regions.json');
            $created = 0;
            $updated = 0;

            foreach ($regions as $region) {
                $name = $this->cleanName($region['denominazione_regione'] ?? $region['name'] ?? '');
                $code = str_pad($region['codice_regione'] ?? $region['code'] ?? '', 2, '0', STR_PAD_LEFT);
                $area = $region['ripartizione_geografica'] ?? $region['geographic_area'] ?? null;

                if (empty($name)) {
                    continue;
                }

                $existing = Region::where('code', $code)->first();

                if ($existing) {
                    $existing->update([
                        'country_id' => $country->id,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'geographic_area' => $area,
                    ]);
                    $updated++;
                } else {
                    Region::create([
                        'country_id' => $country->id,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'code' => $code,
                        'geographic_area' => $area,
                    ]);
                    $created++;
                }
            }

            return $this->formatSyncResult($created, $updated);
        });
    }

    private function seedProvinces(string $countryPath, Country $country): void
    {
        $this->components->task("  Seeding provinces", function () use ($countryPath, $country) {
            $provinces = $this->loadJson($countryPath . '/provinces.json');

            $regionMap = Region::where('country_id', $country->id)
                ->pluck('id', 'code')
                ->toArray();

            $created = 0;
            $updated = 0;

            foreach ($provinces as $province) {
                $name = $this->cleanName($province['denominazione_provincia'] ?? $province['name'] ?? '');
                $code = trim($province['sigla_provincia'] ?? $province['code'] ?? '');
                $regionCode = str_pad($province['codice_regione'] ?? $province['region_code'] ?? '', 2, '0', STR_PAD_LEFT);
                $type = $province['tipologia_provincia'] ?? $province['type'] ?? null;

                if (empty($name) || empty($code)) {
                    continue;
                }

                $regionId = $regionMap[$regionCode] ?? null;
                if (! $regionId) {
                    continue;
                }

                $existing = Province::where('code', $code)->first();

                if ($existing) {
                    $existing->update([
                        'region_id' => $regionId,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'type' => $type,
                    ]);
                    $updated++;
                } else {
                    Province::create([
                        'region_id' => $regionId,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'code' => $code,
                        'type' => $type,
                    ]);
                    $created++;
                }
            }

            return $this->formatSyncResult($created, $updated);
        });
    }

    private function seedCities(string $countryPath, Country $country, bool $hasCap): void
    {
        $this->components->task("  Seeding cities", function () use ($countryPath, $country, $hasCap) {
            $comuni = $this->loadJson($countryPath . '/cities.json');

            // Build CAP map
            $capMap = [];
            if ($hasCap) {
                $capData = $this->loadJson($countryPath . '/cap.json');
                foreach ($capData as $entry) {
                    $istat = trim($entry['codice_istat'] ?? $entry['code'] ?? '');
                    $cap = trim($entry['cap'] ?? $entry['postal_code'] ?? '');
                    if ($istat && $cap) {
                        $capMap[$istat][] = $cap;
                    }
                }
            }

            // Province map for this country
            $regionIds = Region::where('country_id', $country->id)->pluck('id');
            $provinceMap = Province::whereIn('region_id', $regionIds)
                ->pluck('id', 'code')
                ->toArray();

            $created = 0;
            $updated = 0;
            $seenBelfioreCodes = [];

            $chunks = array_chunk($comuni, 500);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $comune) {
                    $name = $this->cleanName($comune['denominazione_ita'] ?? $comune['name'] ?? '');
                    $provinceCode = trim($comune['sigla_provincia'] ?? $comune['province_code'] ?? '');
                    $belfiore = trim($comune['codice_belfiore'] ?? $comune['belfiore_code'] ?? '');
                    $istatCode = trim($comune['codice_istat'] ?? $comune['istat_code'] ?? '');
                    $lat = $comune['lat'] ?? null;
                    $lon = $comune['lon'] ?? null;
                    $isCapital = in_array($comune['flag_capoluogo'] ?? $comune['is_capital'] ?? 'NO', ['SI', 'YES', true, 1], true);

                    if (empty($name) || empty($provinceCode)) {
                        continue;
                    }

                    $provinceId = $provinceMap[$provinceCode] ?? null;
                    if (! $provinceId) {
                        continue;
                    }

                    $caps = $capMap[$istatCode] ?? null;

                    if ($belfiore) {
                        $seenBelfioreCodes[] = $belfiore;
                    }

                    // Upsert: find existing by belfiore_code (stable key)
                    $existing = $belfiore ? City::where('belfiore_code', $belfiore)->first() : null;

                    $data = [
                        'province_id' => $provinceId,
                        'name' => $name,
                        'slug' => Str::slug($name),
                        'belfiore_code' => $belfiore ?: null,
                        'istat_code' => $istatCode ?: null,
                        'cap' => $caps,
                        'lat' => $lat ? (float) $lat : null,
                        'lon' => $lon ? (float) $lon : null,
                        'is_capital' => $isCapital,
                        'is_active' => true,
                    ];

                    if ($existing) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        City::create($data);
                        $created++;
                    }
                }
            }

            // Deactivation sync: mark cities NOT in the JSON as inactive
            // Only for this country's cities (don't touch other countries)
            $deactivated = 0;
            if (! empty($seenBelfioreCodes)) {
                $deactivated = City::whereIn('province_id', Province::whereIn('region_id', $regionIds)->pluck('id'))
                    ->where('is_active', true)
                    ->whereNotNull('belfiore_code')
                    ->whereNotIn('belfiore_code', $seenBelfioreCodes)
                    ->update(['is_active' => false]);
            }

            return $this->formatSyncResult($created, $updated, $deactivated);
        });
    }

    // ──────────────────────────────────────────────
    //  LIST + HELPERS
    // ──────────────────────────────────────────────

    private function listAvailableCountries(string $dataPath): int
    {
        $available = $this->getAvailableCountries($dataPath);

        if (empty($available)) {
            $this->warn('No country data packages found in database/data/countries/');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Available country data packages:');
        $this->newLine();

        foreach ($available as $code) {
            $countryPath = $dataPath . '/countries/' . $code;
            $files = glob($countryPath . '/*.json');
            $totalSize = array_sum(array_map('filesize', $files));
            $sizeMb = round($totalSize / 1024 / 1024, 1);

            $features = array_filter([
                file_exists($countryPath . '/regions.json') ? 'regions' : null,
                file_exists($countryPath . '/provinces.json') ? 'provinces' : null,
                file_exists($countryPath . '/cities.json') ? 'cities' : null,
                file_exists($countryPath . '/cap.json') ? 'CAP' : null,
            ]);

            $isoCode = strtoupper($code);
            $country = Country::where('iso_code', $isoCode)->first();
            $loaded = $country ? Region::where('country_id', $country->id)->exists() : false;
            $status = $loaded ? '<info>LOADED</info>' : '<comment>available</comment>';

            $this->components->twoColumnDetail(
                strtoupper($code) . " [{$status}]",
                implode(', ', $features) . " ({$sizeMb} MB)"
            );
        }

        $this->newLine();
        $this->info('Usage examples:');
        $this->line('  php artisan geodata:seed --country=it              # Seed Italy');
        $this->line('  php artisan geodata:seed --country=it --country=de # Seed Italy + Germany');
        $this->line('  php artisan geodata:seed                           # Re-sync all loaded data');

        return self::SUCCESS;
    }

    private function showSeedingPlan(array $countriesToSeed): void
    {
        $this->newLine();
        $this->info('Geodata Seeding Plan');
        $this->info('====================');

        if (! $this->option('no-countries')) {
            $this->components->twoColumnDetail('World countries', '295 nations (upsert mode)');
        }

        if (empty($countriesToSeed)) {
            $this->components->twoColumnDetail('Detail data', 'None');
        } else {
            foreach ($countriesToSeed as $code) {
                $label = self::COUNTRY_LABELS[$code] ?? strtoupper($code) . ' (regions, provinces, cities)';
                $this->components->twoColumnDetail('Detail: ' . strtoupper($code), $label);
            }
        }

        $this->components->twoColumnDetail('Mode', $this->option('fresh') ? 'FRESH (truncate + reseed)' : 'SYNC (upsert + deactivate removed)');
        $this->newLine();
    }

    private function isFirstRun(): bool
    {
        try {
            return Country::count() === 0;
        } catch (\Exception) {
            return true;
        }
    }

    private function getAvailableCountries(string $dataPath): array
    {
        $countriesPath = $dataPath . '/countries';
        if (! is_dir($countriesPath)) {
            return [];
        }

        $dirs = array_filter(glob($countriesPath . '/*'), 'is_dir');
        return array_map(fn ($dir) => strtolower(basename($dir)), $dirs);
    }

    private function isCountryActive(string $sigla): bool
    {
        if (in_array($sigla, self::HISTORICAL_ISO_CODES)) {
            return false;
        }

        if ($sigla !== '' && is_numeric($sigla)) {
            return false;
        }

        return true;
    }

    private function formatSyncResult(int $created, int $updated, int $deactivated = 0): string
    {
        $parts = [];
        if ($created > 0) $parts[] = "{$created} new";
        if ($updated > 0) $parts[] = "{$updated} updated";
        if ($deactivated > 0) $parts[] = "{$deactivated} deactivated";
        return implode(', ', $parts) ?: 'no changes';
    }

    private function loadJson(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }

    private function cleanName(string $name): string
    {
        $name = trim($name);
        return mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function getDataPath(): string
    {
        return dirname(__DIR__, 2) . '/database/data';
    }
}
