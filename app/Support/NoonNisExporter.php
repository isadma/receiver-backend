<?php

namespace App\Support;

use App\Models\LotItem;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class NoonNisExporter
{
    /**
     * @param  list<string>  $barcodes
     * @return array{parents: int, children: int, missing: list<string>}
     */
    public static function export(string $templatePath, string $outputPath, array $barcodes): array
    {
        $unique = array_values(array_unique(array_filter($barcodes, fn ($barcode) => $barcode !== '')));
        $items = LotItem::query()
            ->with('lot')
            ->whereIn('barcode', $unique)
            ->get()
            ->keyBy('barcode');

        $missing = [];
        $byModel = [];
        foreach ($unique as $barcode) {
            $item = $items->get($barcode);
            if ($item === null || $item->lot === null || trim((string) $item->lot->model) === '') {
                $missing[] = $barcode;

                continue;
            }
            $byModel[$item->lot->model][] = $item;
        }

        ksort($byModel);

        $spreadsheet = ExcelSheet::load($templatePath);
        $sheet = $spreadsheet->getSheetByName('template_data');
        if ($sheet === null) {
            throw new RuntimeException('NIS template is missing the template_data sheet.');
        }

        $columns = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn(9));
        for ($column = 1; $column <= $highestColumn; $column++) {
            $code = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).'9')->getValue());
            if ($code !== '') {
                $columns[$code] = $column;
            }
        }

        $lastDataRow = $sheet->getHighestDataRow();
        if ($lastDataRow >= 10) {
            $sheet->removeRow(10, $lastDataRow - 9);
        }

        $rowNumber = 10;
        $parents = 0;
        $children = 0;

        foreach ($byModel as $modelItems) {
            usort($modelItems, function (LotItem $left, LotItem $right): int {
                $color = strcmp((string) $left->lot?->color_en, (string) $right->lot?->color_en);
                if ($color !== 0) {
                    return $color;
                }

                return strnatcmp($left->size_code, $right->size_code);
            });

            $colors = [];
            foreach ($modelItems as $item) {
                $colors[(string) $item->lot?->color_en] = true;
            }
            $includeColorInSize = count($colors) > 1;

            self::writeRow($sheet, $rowNumber, $columns, NoonNisMapper::parentRow($modelItems[0]));
            $rowNumber++;
            $parents++;

            foreach ($modelItems as $item) {
                self::writeRow($sheet, $rowNumber, $columns, NoonNisMapper::childRow($item, $includeColorInSize));
                $rowNumber++;
                $children++;
            }
        }

        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);

        return [
            'parents' => $parents,
            'children' => $children,
            'missing' => $missing,
        ];
    }

    /**
     * @param  array<string, int>  $columns
     * @param  array<string, string>  $values
     */
    private static function writeRow($sheet, int $row, array $columns, array $values): void
    {
        foreach ($values as $code => $value) {
            $column = $columns[$code] ?? null;
            if ($column === null || $value === '') {
                continue;
            }

            $cell = Coordinate::stringFromColumnIndex($column).$row;
            if (in_array($code, ['seller_sku', 'gtin', 'parent_group_key'], true)) {
                $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);

                continue;
            }

            $sheet->setCellValue($cell, $value);
        }
    }

    /**
     * @param  list<string>  $barcodes
     */
    public static function writePriceSheet(string $outputPath, array $barcodes, string $partnerId = '661132'): int
    {
        $unique = array_values(array_unique(array_filter($barcodes, fn ($barcode) => $barcode !== '')));
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('price');
        $sheet->fromArray([[
            'psku_code',
            'country_code',
            'id_partner',
            'partner_sku',
            'price',
        ]], null, 'A1');

        $row = 2;
        foreach ($unique as $barcode) {
            $sheet->setCellValueExplicit('B'.$row, 'ae', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, $partnerId, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$row, $barcode, DataType::TYPE_STRING);
            $sheet->setCellValue('E'.$row, (int) NoonNisMapper::PRICE_AED);
            $row++;
        }

        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);

        return count($unique);
    }

    public static function fillOfficialPriceUpdate(string $templateCsv, string $outputCsv): int
    {
        $handle = fopen($templateCsv, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not read Noon Price Update template.');
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false || $headers === []) {
                throw new RuntimeException('Noon Price Update template has no header row.');
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? $headers[0];

            $rows = [];
            while (($data = fgetcsv($handle)) !== false) {
                if ($data === [null] || $data === false) {
                    continue;
                }
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = $data[$index] ?? '';
                }
                $row['price'] = NoonNisMapper::PRICE_AED;
                $row['sale_price'] = '';
                $row['sale_start'] = '';
                $row['sale_end'] = '';
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        $directory = dirname($outputCsv);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $out = fopen($outputCsv, 'w');
        if ($out === false) {
            throw new RuntimeException('Could not write Noon Price Update file.');
        }

        fwrite($out, self::csvLine($headers)."\n");
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fwrite($out, self::csvLine($line)."\n");
        }
        fclose($out);

        $xlsxPath = (string) preg_replace('/\.csv$/i', '.xlsx', $outputCsv);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('price');
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 1], $header, DataType::TYPE_STRING);
        }
        foreach ($rows as $rowIndex => $row) {
            foreach ($headers as $index => $header) {
                $value = (string) ($row[$header] ?? '');
                $column = $index + 1;
                $excelRow = $rowIndex + 2;
                if ($header === 'price' && $value !== '') {
                    $sheet->setCellValue([$column, $excelRow], (int) $value);

                    continue;
                }
                $sheet->setCellValueExplicit([$column, $excelRow], $value, DataType::TYPE_STRING);
            }
        }
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($xlsxPath);

        $slimPath = (string) preg_replace('/\.csv$/i', '-slim.csv', $outputCsv);
        $slim = fopen($slimPath, 'w');
        if ($slim === false) {
            throw new RuntimeException('Could not write slim Noon Price Update file.');
        }
        $slimHeaders = ['psku_code', 'country_code', 'id_partner', 'partner_sku', 'price'];
        fwrite($slim, self::csvLine($slimHeaders)."\n");
        foreach ($rows as $row) {
            fwrite($slim, self::csvLine([
                $row['psku_code'] ?? '',
                $row['country_code'] ?? '',
                $row['id_partner'] ?? '',
                $row['partner_sku'] ?? '',
                $row['price'] ?? '',
            ])."\n");
        }
        fclose($slim);

        return count($rows);
    }

    /**
     * @param  list<string>  $fields
     */
    private static function csvLine(array $fields): string
    {
        return implode(',', array_map(
            fn (string $field) => '"'.str_replace('"', '""', $field).'"',
            $fields,
        ));
    }
}
