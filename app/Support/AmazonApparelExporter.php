<?php

namespace App\Support;

use App\Models\LotItem;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class AmazonApparelExporter
{
    private const LABEL_ROW = 4;

    private const DATA_ROW = 7;

    /**
     * @var list<string>
     */
    private const STRING_FIELDS = ['SKU', 'Parent SKU', 'Product Id'];

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
        $sheet = $spreadsheet->getSheetByName('Template');
        if ($sheet === null) {
            throw new RuntimeException('Amazon apparel template is missing the Template sheet.');
        }

        $columns = [];
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestColumn(self::LABEL_ROW));
        for ($column = 1; $column <= $highestColumn; $column++) {
            $label = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($column).self::LABEL_ROW)->getValue());
            if ($label === '') {
                continue;
            }
            $columns[$label][] = $column;
        }

        $lastDataRow = $sheet->getHighestDataRow();
        if ($lastDataRow >= self::DATA_ROW) {
            $sheet->removeRow(self::DATA_ROW, $lastDataRow - (self::DATA_ROW - 1));
        }

        $rowNumber = self::DATA_ROW;
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

            self::writeRow($sheet, $rowNumber, $columns, AmazonApparelMapper::parentRow($modelItems[0]));
            $rowNumber++;
            $parents++;

            foreach ($modelItems as $item) {
                self::writeRow($sheet, $rowNumber, $columns, AmazonApparelMapper::childRow($item));
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
     * @param  array<string, list<int>>  $columns
     * @param  array<string, string|list<string>>  $values
     */
    private static function writeRow($sheet, int $row, array $columns, array $values): void
    {
        $used = [];
        foreach ($values as $label => $value) {
            $items = is_array($value) ? $value : [$value];
            foreach ($items as $item) {
                if ($item === '') {
                    $used[$label] = ($used[$label] ?? 0) + 1;

                    continue;
                }

                $indexes = $columns[$label] ?? [];
                $offset = $used[$label] ?? 0;
                $column = $indexes[$offset] ?? null;
                $used[$label] = $offset + 1;
                if ($column === null) {
                    continue;
                }

                $cell = Coordinate::stringFromColumnIndex($column).$row;
                if (in_array($label, self::STRING_FIELDS, true)) {
                    $sheet->setCellValueExplicit($cell, $item, DataType::TYPE_STRING);

                    continue;
                }

                $sheet->setCellValue($cell, $item);
            }
        }
    }
}
