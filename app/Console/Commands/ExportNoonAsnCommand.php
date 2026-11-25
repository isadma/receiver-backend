<?php

namespace App\Console\Commands;

use App\Support\NoonAsnExporter;
use Illuminate\Console\Command;

class ExportNoonAsnCommand extends Command
{
    protected $signature = 'noon:export-asns
        {receiver : Receiver xlsx with shop, box, barcode, size, qty}
        {output : Directory for ASN-*.xlsx files}
        {--template= : Noon ASN bulk template xlsx}';

    protected $description = 'Write one Noon ASN file per receiver box.';

    public function handle(): int
    {
        $receiver = (string) $this->argument('receiver');
        $output = (string) $this->argument('output');
        $template = (string) ($this->option('template') ?: '/Users/maksat/Downloads/asn_bulk_template_blank_2026-11-25.xlsx');

        $result = NoonAsnExporter::export($receiver, $template, $output);

        $this->info("Wrote {$result['boxes']} ASN files to {$output}.");
        if ($result['unknown_sizes'] !== []) {
            $this->warn('Used kids pack for unknown sizes: '.implode(', ', $result['unknown_sizes']));
        }

        return self::SUCCESS;
    }
}
