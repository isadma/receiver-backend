<?php

namespace App\Console\Commands;

use App\Support\ExcelSheet;
use App\Support\NuriCatalogUpserter;
use Illuminate\Console\Command;

class ImportNuriCatalogCommand extends Command
{
    protected $signature = 'catalog:import-nuri
        {file : Path to a Nuri xlsx}
        {--origin= : Bangladesh or Egypt}
        {--barcodes= : Receiver xlsx with the barcode allowlist}';

    protected $description = 'Upsert Nuri catalog rows for barcodes that appear in the receiver Excel. Does not wipe receipts.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $origin = (string) $this->option('origin');
        $barcodesPath = (string) $this->option('barcodes');

        if ($origin === '' || $barcodesPath === '') {
            $this->error('Pass --origin and --barcodes.');

            return self::FAILURE;
        }

        $barcodes = ExcelSheet::barcodesFromReceiver($barcodesPath);
        $result = NuriCatalogUpserter::import($file, $origin, $barcodes);

        $this->info("Imported {$result['items']} items in {$result['lots']} lots. Skipped {$result['skipped']} extra Nuri rows.");

        return self::SUCCESS;
    }
}
