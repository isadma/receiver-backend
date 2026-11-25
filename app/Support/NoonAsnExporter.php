<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class NoonAsnExporter
{
    private const HEADER_ROW = 10;

    private const DATA_ROW = 11;

    /**
     * @var array{longest: float, median: float, shortest: float, weight: float}
     */
    public const KIDS_PACK = [
        'longest' => 30.0,
        'median' => 25.0,
        'shortest' => 2.0,
        'weight' => 0.25,
    ];

    /**
     * @var array{longest: float, median: float, shortest: float, weight: float}
     */
    public const ADULT_PACK = [
        'longest' => 35.0,
        'median' => 25.0,
        'shortest' => 1.0,
        'weight' => 0.40,
    ];

    /**
     * @return array{boxes: int, files: list<string>, unknown_sizes: list<string>}
     */
    public static function export(string $receiverPath, string $templatePath, string $outputDir): array
    {
        $boxes = self::groupNoonBoxes($receiverPath);
        if ($boxes === []) {
            throw new RuntimeException('No Noon rows found in the receiver file.');
        }

        if (! is_dir($outputDir) && ! mkdir($outputDir, 0777, true) && ! is_dir($outputDir)) {
            throw new RuntimeException("Could not create ASN directory: {$outputDir}");
        }

        $columns = null;
        $files = [];
        $unknown = [];

        foreach ($boxes as $box => $items) {
            $spreadsheet = ExcelSheet::load($templatePath);
            $sheet = $spreadsheet->getSheetByName('Template');
            if ($sheet === null) {
                throw new RuntimeException('ASN template is missing the Template sheet.');
            }
            if ($columns === null) {
                $columns = self::columns($sheet);
            }

            $rowNumber = self::DATA_ROW;
            foreach ($items as $item) {
                $pack = self::pack($item['size']);
                if (! self::isAgeSize($item['size']) && ! self::isLetterSize($item['size'])) {
                    $unknown[$item['size'] === '' ? '(empty)' : $item['size']] = true;
                }

                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($columns['sku']).$rowNumber,
                    $item['barcode'],
                    DataType::TYPE_STRING,
                );
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columns['qty']).$rowNumber, $item['qty']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columns['longest']).$rowNumber, $pack['longest']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columns['median']).$rowNumber, $pack['median']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columns['shortest']).$rowNumber, $pack['shortest']);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columns['weight']).$rowNumber, $pack['weight']);
                $rowNumber++;
            }

            $path = rtrim($outputDir, '/').'/ASN-'.$box.'.xlsx';
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
            $files[] = $path;
        }

        return [
            'boxes' => count($files),
            'files' => $files,
            'unknown_sizes' => array_keys($unknown),
        ];
    }

    /**
     * @return array{longest: float, median: float, shortest: float, weight: float}
     */
    public static function pack(string $size): array
    {
        return self::isLetterSize($size) ? self::ADULT_PACK : self::KIDS_PACK;
    }

    public static function isAgeSize(string $size): bool
    {
        return preg_match('/^\d+-\d+$/', NoonNisMapper::sizeCode($size)) === 1;
    }

    public static function isLetterSize(string $size): bool
    {
        $code = strtoupper(NoonNisMapper::sizeCode($size));

        return preg_match('/^(XXS|XS|S|M|L|XL|XXL|\d+XL)$/', $code) === 1;
    }

    /**
     * @return array<string, list<array{barcode: string, qty: int, size: string}>>
     */
    public static function groupNoonBoxes(string $receiverPath): array
    {
        $boxes = [];
        foreach (ExcelSheet::assocRows($receiverPath) as $row) {
            $shop = mb_strtolower(trim((string) ($row['Магазин'] ?? $row['shop'] ?? '')));
            if ($shop !== 'noon') {
                continue;
            }

            $box = trim((string) ($row['Короб'] ?? $row['box'] ?? ''));
            $barcode = trim((string) ($row['Штрихкод'] ?? $row['Barkod'] ?? $row['barcode'] ?? ''));
            if ($box === '' || $barcode === '') {
                continue;
            }

            $qty = (int) round((float) str_replace(',', '.', (string) ($row['Кол-во'] ?? $row['qty'] ?? '0')));
            $size = NoonNisMapper::sizeCode((string) ($row['Размер'] ?? $row['size'] ?? ''));
            $key = $barcode;
            if (! isset($boxes[$box][$key])) {
                $boxes[$box][$key] = [
                    'barcode' => $barcode,
                    'qty' => 0,
                    'size' => $size,
                ];
            }
            $boxes[$box][$key]['qty'] += max($qty, 0);
        }

        ksort($boxes, SORT_NATURAL);
        foreach ($boxes as $box => $items) {
            $boxes[$box] = array_values(array_filter($items, fn ($item) => $item['qty'] > 0));
        }

        return array_filter($boxes);
    }

    /**
     * @return array{sku: int, qty: int, longest: int, median: int, shortest: int, weight: int}
     */
    private static function columns($sheet): array
    {
        $wanted = [
            'sku' => 'Partner SKU / SKU',
            'qty' => 'Quantity',
            'longest' => 'Longest Side (cm)',
            'median' => 'Median Side (cm)',
            'shortest' => 'Shortest Side (cm)',
            'weight' => 'Weight (kg)',
        ];
        $found = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn(self::HEADER_ROW));
        for ($column = 1; $column <= $highestColumn; $column++) {
            $label = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).self::HEADER_ROW)->getValue());
            $key = array_search($label, $wanted, true);
            if ($key !== false) {
                $found[$key] = $column;
            }
        }

        $missing = array_diff(array_keys($wanted), array_keys($found));
        if ($missing !== []) {
            throw new RuntimeException('ASN template is missing columns: '.implode(', ', $missing));
        }

        return $found;
    }
}
