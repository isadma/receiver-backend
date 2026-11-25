<?php

namespace App\Support;

use App\Models\Lot;
use App\Models\LotItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NuriCatalogUpserter
{
    /**
     * @param  list<string>  $allowedBarcodes
     * @return array{lots: int, items: int, skipped: int}
     */
    public static function import(string $path, string $origin, array $allowedBarcodes): array
    {
        $allow = array_fill_keys($allowedBarcodes, true);
        $rows = [];
        $skipped = 0;

        foreach (ExcelSheet::assocRows($path) as $raw) {
            $parsed = self::parseRow($raw);
            if ($parsed === null) {
                continue;
            }
            $barcode = $parsed['barcode'];
            if ($barcode === '') {
                continue;
            }
            if (! isset($allow[$barcode])) {
                $skipped++;

                continue;
            }
            if (isset($rows[$barcode])) {
                continue;
            }
            $parsed['country_of_origin'] = $origin;
            $rows[$barcode] = $parsed;
        }

        if ($rows === []) {
            return ['lots' => 0, 'items' => 0, 'skipped' => $skipped];
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $parsed) {
                $lot = Lot::query()->updateOrCreate(
                    ['lot_barcode' => $parsed['lot_barcode']],
                    [
                        'invoice_date' => $parsed['invoice_date'],
                        'group_name' => $parsed['group_name'],
                        'brand' => $parsed['brand'],
                        'model' => $parsed['model'],
                        'assortment' => $parsed['assortment'],
                        'size_set' => $parsed['size_set'],
                        'fabric_en' => $parsed['fabric_en'],
                        'name_en' => $parsed['name_en'],
                        'name_ru' => $parsed['name_ru'],
                        'color' => $parsed['color'],
                        'color_en' => $parsed['color_en'],
                        'variant' => $parsed['variant'],
                        'usd' => $parsed['usd'],
                        'currency' => $parsed['currency'],
                        'country_of_origin' => $parsed['country_of_origin'],
                        'invoice_lot_qty' => $parsed['invoice_lot_qty'],
                    ],
                );

                $item = LotItem::query()->where('barcode', $parsed['barcode'])->first();
                $values = [
                    'size_code' => $parsed['size_code'],
                    'pieces_per_lot' => CatalogImporter::piecesPerLot(
                        $parsed['assortment'],
                        $parsed['size_set'],
                        $parsed['size_code'],
                    ),
                    'invoice_qty' => $parsed['invoice_qty'],
                ];

                if ($item) {
                    if ($item->lot_id !== $lot->id && $item->receipts()->doesntExist()) {
                        $values['lot_id'] = $lot->id;
                    } elseif ($item->lot_id !== $lot->id) {
                        $item->lot?->update([
                            'brand' => $parsed['brand'],
                            'model' => $parsed['model'],
                            'fabric_en' => $parsed['fabric_en'],
                            'name_en' => $parsed['name_en'],
                            'name_ru' => $parsed['name_ru'],
                            'color' => $parsed['color'],
                            'color_en' => $parsed['color_en'],
                            'variant' => $parsed['variant'],
                            'country_of_origin' => $parsed['country_of_origin'],
                        ]);
                    }
                    $item->update($values);

                    continue;
                }

                $lot->items()->create([
                    'barcode' => $parsed['barcode'],
                    ...$values,
                ]);
            }
        });

        return [
            'lots' => collect($rows)->pluck('lot_barcode')->unique()->count(),
            'items' => count($rows),
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, string>  $raw
     * @return array<string, mixed>|null
     */
    public static function parseRow(array $raw): ?array
    {
        $normalized = [];
        foreach ($raw as $key => $value) {
            $normalized[self::normalizeHeader((string) $key)] = $value;
        }

        $get = function (string ...$keys) use ($normalized): string {
            foreach ($keys as $key) {
                $lookup = self::normalizeHeader($key);
                if (isset($normalized[$lookup]) && trim($normalized[$lookup]) !== '') {
                    return trim($normalized[$lookup]);
                }
            }

            return '';
        };

        $lotBarcode = strtoupper($get('Lot Barkod', 'lot_barcode'));
        $barcode = $get('Barkod', 'barcode');
        $size = $get('BedenKodu', 'BedenAdı', 'size_code');
        $size = trim((string) preg_replace('/\s*Ya[sşŞ]\s*$/iu', '', $size));

        if ($lotBarcode === '' && $barcode === '') {
            return null;
        }

        if ($lotBarcode === '') {
            throw new RuntimeException('Nuri row is missing Lot Barkod.');
        }

        return [
            'invoice_date' => $get('FaturaTarih', 'invoice_date'),
            'group_name' => $get('Grup', 'group_name'),
            'brand' => $get('Marka', 'brand'),
            'model' => $get('Model No', 'model'),
            'assortment' => $get('Asorti', 'assortment'),
            'size_set' => $get('BedenSeti', 'size_set'),
            'fabric_en' => $get('Kumaş İngilizce', 'fabric_en'),
            'name_en' => $get('İngilizce Tanım', 'name_en'),
            'name_ru' => $get('Rusca Tanım', 'name_ru'),
            'invoice_lot_qty' => (int) round((float) str_replace(',', '.', $get('LotAdeti', 'invoice_lot_qty'))),
            'color' => $get('Renk', 'color'),
            'color_en' => $get('İngilizce Renk', 'color_en'),
            'invoice_qty' => (int) round((float) str_replace(',', '.', $get('Adet', 'invoice_qty'))),
            'lot_barcode' => $lotBarcode,
            'barcode' => $barcode,
            'variant' => $get('Varyant', 'variant'),
            'size_code' => $size,
            'usd' => $get('USD', 'usd'),
            'currency' => $get('Döviz', 'currency'),
        ];
    }

    private static function normalizeHeader(string $header): string
    {
        $header = trim(str_replace("\u{00A0}", ' ', $header));
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return mb_strtolower($header);
    }
}
