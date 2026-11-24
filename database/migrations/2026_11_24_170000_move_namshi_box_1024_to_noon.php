<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FROM_SHOP = 'namshi';

    private const TO_SHOP = 'noon';

    private const BOX = '1024';

    public function up(): void
    {
        $this->moveBox(self::FROM_SHOP, self::TO_SHOP);
    }

    public function down(): void
    {
        $this->moveBox(self::TO_SHOP, self::FROM_SHOP);
    }

    private function moveBox(string $fromShop, string $toShop): void
    {
        DB::transaction(function () use ($fromShop, $toShop): void {
            $receipts = DB::table('box_receipts')
                ->where('shop_slug', $fromShop)
                ->where('box_number', self::BOX)
                ->orderBy('id')
                ->get();

            foreach ($receipts as $receipt) {
                $qty = (int) $receipt->qty;

                $item = DB::table('lot_items')
                    ->where('id', $receipt->lot_item_id)
                    ->lockForUpdate()
                    ->first();

                if ($item !== null && $qty > 0) {
                    $fromColumn = $fromShop.'_received_qty';
                    $toColumn = $toShop.'_received_qty';

                    DB::table('lot_items')->where('id', $item->id)->update([
                        $fromColumn => max(0, (int) $item->{$fromColumn} - $qty),
                        $toColumn => (int) $item->{$toColumn} + $qty,
                    ]);
                }

                $existing = DB::table('box_receipts')
                    ->where('shop_slug', $toShop)
                    ->where('box_number', self::BOX)
                    ->where('lot_item_id', $receipt->lot_item_id)
                    ->where('id', '!=', $receipt->id)
                    ->first();

                if ($existing) {
                    DB::table('box_receipts')->where('id', $existing->id)->update([
                        'qty' => (int) $existing->qty + $qty,
                    ]);
                    DB::table('box_receipts')->where('id', $receipt->id)->delete();

                    continue;
                }

                DB::table('box_receipts')->where('id', $receipt->id)->update([
                    'shop_slug' => $toShop,
                ]);
            }
        });
    }
};
