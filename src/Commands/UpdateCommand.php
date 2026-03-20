<?php

namespace DiegoCopat\LaravelGeodata\Commands;

use Illuminate\Console\Command;

class UpdateCommand extends Command
{
    protected $signature = 'geodata:update
        {--source= : URL base for JSON data files (default: DarioCorno GitHub)}
        {--dry-run : Show what would change without modifying data}';

    protected $description = 'Update geographic data from remote source';

    private const DEFAULT_BASE_URL = 'https://raw.githubusercontent.com/DarioCorno/database_comuni_italiani/master/json';

    private const FILES = [
        'gi_nazioni.json',
        'gi_regioni.json',
        'gi_province.json',
        'gi_comuni.json',
        'gi_cap.json',
    ];

    public function handle(): int
    {
        $baseUrl = $this->option('source') ?? self::DEFAULT_BASE_URL;
        $dataPath = dirname(__DIR__, 2) . '/database/data';
        $isDryRun = $this->option('dry-run');

        $this->info('Updating geodata from remote source...');
        $this->newLine();

        $updated = false;

        foreach (self::FILES as $file) {
            $url = rtrim($baseUrl, '/') . '/' . $file;
            $localPath = $dataPath . '/' . $file;

            $this->components->task("Downloading {$file}", function () use ($url, $localPath, $isDryRun, &$updated) {
                $content = @file_get_contents($url);

                if ($content === false) {
                    return 'FAILED - Could not download';
                }

                $newData = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return 'FAILED - Invalid JSON';
                }

                $newCount = count($newData);
                $oldCount = 0;

                if (file_exists($localPath)) {
                    $oldData = json_decode(file_get_contents($localPath), true) ?? [];
                    $oldCount = count($oldData);
                }

                if ($isDryRun) {
                    $diff = $newCount - $oldCount;
                    $sign = $diff >= 0 ? '+' : '';
                    return "DRY RUN: {$oldCount} → {$newCount} ({$sign}{$diff})";
                }

                file_put_contents($localPath, $content);
                $updated = true;

                $diff = $newCount - $oldCount;
                $sign = $diff >= 0 ? '+' : '';
                return "{$oldCount} → {$newCount} ({$sign}{$diff})";
            });
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn('Dry run complete. No files were modified.');
            $this->info('Run without --dry-run to apply changes, then run geodata:seed --fresh to reload data.');
        } elseif ($updated) {
            $this->info('Data files updated. Run "php artisan geodata:seed --fresh" to reload into database.');
        } else {
            $this->info('No changes detected.');
        }

        return self::SUCCESS;
    }
}
