<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ExcelSheet
{
    /**
     * @return list<string>
     */
    public static function barcodesFromReceiver(string $path): array
    {
        $seen = [];
        foreach (self::assocRows($path) as $row) {
            $barcode = trim((string) ($row['Штрихкод'] ?? $row['Barkod'] ?? $row['barcode'] ?? ''));
            if ($barcode === '') {
                continue;
            }
            $seen[$barcode] = true;
        }

        return array_keys($seen);
    }

    /**
     * @return list<array<string, string>>
     */
    public static function assocRows(string $path, int $headerRow = 1): array
    {
        $sheet = self::firstSheet($path);
        $highestColumn = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestDataRow();
        $columnCount = Coordinate::columnIndexFromString($highestColumn);

        $headers = [];
        for ($column = 1; $column <= $columnCount; $column++) {
            $headers[$column] = self::cellString($sheet->getCell(Coordinate::stringFromColumnIndex($column).$headerRow));
        }

        $rows = [];
        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $item = [];
            $empty = true;
            for ($column = 1; $column <= $columnCount; $column++) {
                $header = $headers[$column];
                if ($header === '') {
                    continue;
                }
                $value = self::cellString($sheet->getCell(Coordinate::stringFromColumnIndex($column).$row));
                $item[$header] = $value;
                if ($value !== '') {
                    $empty = false;
                }
            }
            if (! $empty) {
                $rows[] = $item;
            }
        }

        return $rows;
    }

    private static function cellString(Cell $cell): string
    {
        $value = $cell->getValue();
        if (is_int($value) || (is_float($value) && fmod($value, 1.0) === 0.0)) {
            return sprintf('%.0f', $value);
        }

        return trim((string) $cell->getFormattedValue());
    }

    public static function firstSheet(string $path): Worksheet
    {
        if (! is_file($path)) {
            throw new RuntimeException("Excel file not found: {$path}");
        }

        return IOFactory::load($path)->getActiveSheet();
    }

    public static function load(string $path): Spreadsheet
    {
        if (! is_file($path)) {
            throw new RuntimeException("Excel file not found: {$path}");
        }

        return IOFactory::load($path);
    }
}
