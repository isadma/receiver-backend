<?php

namespace App\Console\Commands;

use App\Support\AmazonApparelExporter;
use App\Support\ExcelSheet;
use Illuminate\Console\Command;

class ExportAmazonApparelCommand extends Command
{
    protected $signature = 'amazon:export-apparel
        {barcodes : Receiver xlsx with the barcode list}
        {output : Path for the filled Amazon apparel xlsx}
        {--template= : Amazon APPAREL.xlsm template}';

    protected $description = 'Fill an Amazon AE APPAREL template using receiver barcodes and catalog details.';

    public function handle(): int
    {
        $barcodesPath = (string) $this->argument('barcodes');
        $output = (string) $this->argument('output');
        $template = (string) ($this->option('template') ?: '/Users/maksat/Downloads/APPAREL.xlsm');

        $barcodes = ExcelSheet::barcodesFromReceiver($barcodesPath);
        $result = AmazonApparelExporter::export($template, $output, $barcodes);

        $this->info("Wrote {$result['parents']} parents and {$result['children']} children to {$output}.");
        if ($result['missing'] !== []) {
            $this->warn('Missing catalog rows for '.count($result['missing']).' barcodes.');
        }

        return $result['missing'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
