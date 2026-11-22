<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DemoCatalog
{
    public const EXPECTED_PER_SHOP = 2;

    public const TOTAL_EXPECTED = 6;

    /**
     * @return list<array{sku: string, name: string}>
     */
    public static function definitions(): array
    {
        return [
            ['sku' => 'USB2866', 'name' => 'Toddler white T-shirt'],
            ['sku' => 'USB3102', 'name' => 'Toddler navy shorts'],
            ['sku' => 'USB4471', 'name' => 'Kids pink hoodie'],
            ['sku' => 'USB1284', 'name' => 'Baby grey onesie'],
            ['sku' => 'USB5590', 'name' => 'Toddler striped leggings'],
            ['sku' => 'USB6723', 'name' => 'Kids blue jeans'],
            ['sku' => 'USB8045', 'name' => 'Toddler yellow raincoat'],
            ['sku' => 'USB2198', 'name' => 'Kids green polo shirt'],
            ['sku' => 'USB9361', 'name' => 'Baby white socks'],
            ['sku' => 'USB1547', 'name' => 'Toddler denim jacket'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(): array
    {
        $now = now();

        return array_map(
            fn (array $item): array => [
                'barcode' => null,
                'sku' => $item['sku'],
                'name' => $item['name'],
                'total_expected_qty' => self::TOTAL_EXPECTED,
                'amazon_expected_qty' => self::EXPECTED_PER_SHOP,
                'noon_expected_qty' => self::EXPECTED_PER_SHOP,
                'namshi_expected_qty' => self::EXPECTED_PER_SHOP,
                'total_received_qty' => 0,
                'amazon_received_qty' => 0,
                'noon_received_qty' => 0,
                'namshi_received_qty' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::definitions(),
        );
    }

    public static function seedWithoutQuantities(): void
    {
        $now = now();

        DB::table('products')->insert(array_map(
            fn (array $item): array => [
                'barcode' => null,
                'sku' => $item['sku'],
                'name' => $item['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            self::definitions(),
        ));
    }

    public static function applyExpectedQuantities(): void
    {
        DB::table('products')->update([
            'total_expected_qty' => self::TOTAL_EXPECTED,
            'amazon_expected_qty' => self::EXPECTED_PER_SHOP,
            'noon_expected_qty' => self::EXPECTED_PER_SHOP,
            'namshi_expected_qty' => self::EXPECTED_PER_SHOP,
            'total_received_qty' => 0,
            'amazon_received_qty' => 0,
            'noon_received_qty' => 0,
            'namshi_received_qty' => 0,
        ]);
    }

    public static function reset(): void
    {
        DB::table('products')->delete();
        DB::table('products')->insert(self::rows());
    }
}
