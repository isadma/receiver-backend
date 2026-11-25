<?php

namespace App\Console\Commands;

use App\Support\ExcelSheet;
use App\Support\NoonNisExporter;
use App\Support\NoonNisMapper;
use Illuminate\Console\Command;

class ExportNoonNisCommand extends Command
{
    protected $signature = 'noon:export-nis
        {barcodes : Receiver xlsx with the barcode list}
        {output : Path for the filled NIS xlsx}
        {--template= : NIS template xlsx}
        {--partner-id= : Noon id_partner for the Price Update file}';

    protected $description = 'Fill a Noon NIS template using receiver barcodes and catalog details.';

    public function handle(): int
    {
        $barcodesPath = (string) $this->argument('barcodes');
        $output = (string) $this->argument('output');
        $template = (string) ($this->option('template') ?: '/Users/maksat/Downloads/NIS.xlsx');

        $barcodes = ExcelSheet::barcodesFromReceiver($barcodesPath);
        $result = NoonNisExporter::export($template, $output, $barcodes);
        $pricePath = (string) preg_replace('/\.xlsx$/i', '-price.xlsx', $output);
        $partnerId = trim((string) $this->option('partner-id'));
        $priced = NoonNisExporter::writePriceSheet(
            $pricePath,
            $barcodes,
            $partnerId !== '' ? $partnerId : '661132',
        );

        $this->info("Wrote {$result['parents']} parents and {$result['children']} children to {$output}.");
        $this->info("Wrote {$priced} AED ".NoonNisMapper::PRICE_AED." prices to {$pricePath}.");
        if ($result['missing'] !== []) {
            $this->warn('Missing catalog rows for '.count($result['missing']).' barcodes.');
        }

        return $result['missing'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
