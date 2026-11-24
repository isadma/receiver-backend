<?php

namespace App\Support;

use App\Models\Box;
use App\Models\BoxReceipt;
use App\Models\Lot;
use App\Models\LotItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogImporter
{
    /**
     * @return array{lots: int, items: int}
     */
    public static function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Catalog CSV not found: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open catalog CSV: {$path}");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException('Catalog CSV is empty.');
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? $header[0];

            $lots = [];

            while (($raw = fgetcsv($handle)) !== false) {
                if (count(array_filter($raw, fn ($value) => $value !== null && $value !== '')) === 0) {
                    continue;
                }

                $row = self::normalizeRow($raw);
                $lotBarcode = strtoupper(trim((string) $row['lot_barcode']));
                if ($lotBarcode === '') {
                    continue;
                }

                if (! isset($lots[$lotBarcode])) {
                    $lots[$lotBarcode] = [
                        'lot' => [
                            'lot_barcode' => $lotBarcode,
                            'invoice_date' => $row['invoice_date'],
                            'group_name' => $row['group_name'],
                            'brand' => $row['brand'],
                            'model' => $row['model'],
                            'assortment' => $row['assortment'],
                            'size_set' => $row['size_set'],
                            'fabric_en' => $row['fabric_en'],
                            'name_en' => $row['name_en'],
                            'name_ru' => $row['name_ru'],
                            'color' => $row['color'],
                            'color_en' => $row['color_en'],
                            'variant' => $row['variant'],
                            'usd' => $row['usd'],
                            'currency' => $row['currency'],
                            'invoice_lot_qty' => $row['invoice_lot_qty'],
                        ],
                        'items' => [],
                    ];
                }

                $barcode = trim((string) $row['barcode']);
                if ($barcode === '' || isset($lots[$lotBarcode]['items'][$barcode])) {
                    continue;
                }

                $lots[$lotBarcode]['items'][$barcode] = [
                    'barcode' => $barcode,
                    'size_code' => $row['size_code'],
                    'pieces_per_lot' => self::piecesPerLot($row['assortment'], $row['size_set'], $row['size_code']),
                    'invoice_qty' => $row['invoice_qty'],
                ];
            }
        } finally {
            fclose($handle);
        }

        DB::transaction(function () use ($lots): void {
            BoxReceipt::query()->delete();
            Box::query()->delete();
            LotItem::query()->delete();
            Lot::query()->delete();

            foreach ($lots as $payload) {
                $lot = Lot::query()->create($payload['lot']);

                foreach ($payload['items'] as $item) {
                    $lot->items()->create($item);
                }
            }
        });

        return [
            'lots' => count($lots),
            'items' => collect($lots)->sum(fn (array $payload) => count($payload['items'])),
        ];
    }

    public static function resetReceived(): void
    {
        BoxReceipt::query()->delete();
        LotItem::query()->update([
            'amazon_received_qty' => 0,
            'noon_received_qty' => 0,
            'namshi_received_qty' => 0,
        ]);
    }

    /**
     * @param  list<string|null>  $raw
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $raw): array
    {
        $raw = array_map(fn ($value) => trim((string) $value), $raw);

        if (isset($raw[13]) && ! str_starts_with($raw[13], 'KV') && isset($raw[14]) && str_starts_with($raw[14], 'KV')) {
            $sizeCode = preg_replace('/\s*Ya[sşŞ]\s*$/iu', '', $raw[8]) ?? $raw[8];

            return [
                'invoice_date' => $raw[0] ?? '',
                'group_name' => $raw[1] ?? '',
                'brand' => $raw[2] ?? '',
                'model' => $raw[3] ?? '',
                'assortment' => $raw[4] ?? '',
                'size_set' => $raw[5] ?? '',
                'fabric_en' => $raw[6] ?? '',
                'name_en' => $raw[7] ?? '',
                'name_ru' => $raw[9] ?? '',
                'invoice_lot_qty' => self::toInt($raw[10] ?? '0'),
                'color' => $raw[11] ?? '',
                'color_en' => $raw[12] ?? '',
                'invoice_qty' => self::toInt($raw[13] ?? '0'),
                'lot_barcode' => $raw[14] ?? '',
                'barcode' => $raw[15] ?? '',
                'variant' => $raw[16] ?? '',
                'size_code' => $sizeCode,
                'usd' => $raw[17] ?? '',
                'currency' => $raw[18] ?? '',
            ];
        }

        return [
            'invoice_date' => $raw[0] ?? '',
            'group_name' => $raw[1] ?? '',
            'brand' => $raw[2] ?? '',
            'model' => $raw[3] ?? '',
            'assortment' => $raw[4] ?? '',
            'size_set' => $raw[5] ?? '',
            'fabric_en' => $raw[6] ?? '',
            'name_en' => $raw[7] ?? '',
            'name_ru' => $raw[8] ?? '',
            'invoice_lot_qty' => self::toInt($raw[9] ?? '0'),
            'color' => $raw[10] ?? '',
            'color_en' => $raw[11] ?? '',
            'invoice_qty' => self::toInt($raw[12] ?? '0'),
            'lot_barcode' => $raw[13] ?? '',
            'barcode' => $raw[14] ?? '',
            'variant' => $raw[15] ?? '',
            'size_code' => $raw[16] ?? '',
            'usd' => $raw[17] ?? '',
            'currency' => $raw[18] ?? '',
        ];
    }

    public static function piecesPerLot(string $assortment, string $sizeSet, string $sizeCode): int
    {
        $ratios = array_values(array_filter(explode('-', $assortment), fn ($value) => $value !== ''));
        $sizes = array_values(array_filter(explode('/', $sizeSet), fn ($value) => $value !== ''));
        $index = array_search($sizeCode, $sizes, true);

        if ($index === false || ! isset($ratios[$index])) {
            return 1;
        }

        return max(1, (int) $ratios[$index]);
    }

    private static function toInt(string $value): int
    {
        return (int) round((float) str_replace(',', '.', $value));
    }
}
