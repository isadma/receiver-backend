<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\BoxReceipt;
use App\Models\LotItem;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BoxController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const RECEIVED_COLUMNS = [
        'amazon' => 'amazon_received_qty',
        'noon' => 'noon_received_qty',
        'namshi' => 'namshi_received_qty',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop' => ['sometimes', 'string', Rule::exists('shops', 'slug')],
        ]);

        $boxes = Box::query()
            ->when(isset($validated['shop']), fn ($query) => $query->where('shop_slug', $validated['shop']))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")
            ->orderBy('shop_slug')
            ->orderBy('box_number')
            ->get();

        return response()->json([
            'data' => $boxes->map(fn (Box $box) => $this->boxPayload($box))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop' => ['required', 'string', Rule::exists('shops', 'slug')],
            'box' => ['required', 'string', 'max:64'],
        ], [
            'shop.required' => 'Выберите магазин.',
            'shop.exists' => 'Неизвестный магазин.',
            'box.required' => 'Введите номер короба.',
        ]);

        $boxNumber = trim((string) $validated['box']);
        if ($boxNumber === '') {
            throw ValidationException::withMessages([
                'box' => 'Введите номер короба.',
            ]);
        }

        $existing = Box::findByShopAndNumber($validated['shop'], $boxNumber);
        if ($existing) {
            return response()->json([
                'data' => [
                    ...$this->boxPayload($existing),
                    'exists' => true,
                ],
            ]);
        }

        $box = Box::query()->create([
            'shop_slug' => $validated['shop'],
            'box_number' => $boxNumber,
            'status' => Box::STATUS_OPEN,
        ]);

        return response()->json([
            'data' => [
                ...$this->boxPayload($box),
                'exists' => false,
            ],
        ], 201);
    }

    public function show(string $shop, string $boxNumber): JsonResponse
    {
        $box = $this->findBox($shop, $boxNumber);
        $receipts = BoxReceipt::query()
            ->with(['lot', 'item'])
            ->where('shop_slug', $box->shop_slug)
            ->where('box_number', $box->box_number)
            ->orderBy('lot_id')
            ->orderBy('id')
            ->get();

        $lots = $receipts->groupBy('lot_id')->map(function ($rows) {
            $lot = $rows->first()->lot;

            return [
                'id' => $lot?->id,
                'lot_barcode' => $lot?->lot_barcode,
                'model' => $lot?->model,
                'name' => $lot?->name_ru ?: $lot?->name_en,
                'color' => $lot?->color_en ?: $lot?->color,
                'qty' => (int) $rows->sum('qty'),
                'items' => $rows->map(fn (BoxReceipt $receipt) => [
                    'id' => $receipt->id,
                    'lot_item_id' => $receipt->lot_item_id,
                    'barcode' => $receipt->item?->barcode,
                    'size_code' => $receipt->item?->size_code,
                    'qty' => $receipt->qty,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'data' => [
                ...$this->boxPayload($box),
                'lots' => $lots,
            ],
        ]);
    }

    public function close(string $shop, string $boxNumber): JsonResponse
    {
        $box = $this->findBox($shop, $boxNumber);
        $box->update(['status' => Box::STATUS_CLOSED]);

        return response()->json(['data' => $this->boxPayload($box->refresh())]);
    }

    public function open(string $shop, string $boxNumber): JsonResponse
    {
        $box = $this->findBox($shop, $boxNumber);
        $box->update(['status' => Box::STATUS_OPEN]);

        return response()->json(['data' => $this->boxPayload($box->refresh())]);
    }

    public function moveShop(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_shop' => ['required', 'string', Rule::exists('shops', 'slug')],
            'to_shop' => ['required', 'string', Rule::exists('shops', 'slug')],
            'box' => ['required', 'string', 'max:64'],
        ], [
            'from_shop.required' => 'Выберите магазин.',
            'to_shop.required' => 'Выберите магазин.',
            'box.required' => 'Введите номер короба.',
        ]);

        $fromShop = $validated['from_shop'];
        $toShop = $validated['to_shop'];
        $boxNumber = trim((string) $validated['box']);

        if ($fromShop === $toShop) {
            throw ValidationException::withMessages([
                'to_shop' => 'Выберите другой магазин.',
            ]);
        }

        $fromColumn = self::RECEIVED_COLUMNS[$fromShop] ?? null;
        $toColumn = self::RECEIVED_COLUMNS[$toShop] ?? null;
        if ($fromColumn === null || $toColumn === null) {
            throw ValidationException::withMessages([
                'to_shop' => 'Этот магазин нельзя использовать для приёмки.',
            ]);
        }

        $box = $this->findBox($fromShop, $boxNumber);

        DB::transaction(function () use ($box, $fromShop, $toShop, $boxNumber, $fromColumn, $toColumn): void {
            $receipts = BoxReceipt::query()
                ->where('shop_slug', $fromShop)
                ->where('box_number', $boxNumber)
                ->lockForUpdate()
                ->get();

            foreach ($receipts as $receipt) {
                $qty = (int) $receipt->qty;
                $item = LotItem::query()->whereKey($receipt->lot_item_id)->lockForUpdate()->first();

                if ($item !== null && $qty > 0) {
                    LotItem::query()->whereKey($item->id)->update([
                        $fromColumn => max(0, (int) $item->{$fromColumn} - $qty),
                        $toColumn => (int) $item->{$toColumn} + $qty,
                    ]);
                }

                $existing = BoxReceipt::query()
                    ->where('shop_slug', $toShop)
                    ->where('box_number', $boxNumber)
                    ->where('lot_item_id', $receipt->lot_item_id)
                    ->where('id', '!=', $receipt->id)
                    ->first();

                if ($existing) {
                    $existing->increment('qty', $qty);
                    $receipt->delete();

                    continue;
                }

                $receipt->update(['shop_slug' => $toShop]);
            }

            $target = Box::findByShopAndNumber($toShop, $boxNumber);
            if ($target && $target->id !== $box->id) {
                $box->delete();
            } else {
                $box->update(['shop_slug' => $toShop]);
            }
        });

        $moved = Box::findByShopAndNumber($toShop, $boxNumber) ?? $box->refresh();

        return response()->json(['data' => $this->boxPayload($moved)]);
    }

    public function updateReceipt(Request $request, BoxReceipt $receipt): JsonResponse
    {
        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ], [
            'qty.required' => 'Введите количество.',
            'qty.min' => 'Количество должно быть больше нуля.',
        ]);

        $qty = (int) $validated['qty'];
        $column = self::RECEIVED_COLUMNS[$receipt->shop_slug] ?? null;
        if ($column === null) {
            abort(422, 'Этот магазин нельзя использовать для приёмки.');
        }

        DB::transaction(function () use ($receipt, $qty, $column): void {
            $locked = BoxReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $delta = $qty - (int) $locked->qty;
            if ($delta === 0) {
                return;
            }

            $item = LotItem::query()->whereKey($locked->lot_item_id)->lockForUpdate()->firstOrFail();
            $next = (int) $item->{$column} + $delta;
            LotItem::query()->whereKey($item->id)->update([
                $column => max(0, $next),
            ]);
            $locked->update(['qty' => $qty]);
        });

        return response()->json([
            'data' => [
                'id' => $receipt->refresh()->id,
                'qty' => $receipt->qty,
            ],
        ]);
    }

    public function destroyReceipt(BoxReceipt $receipt): JsonResponse
    {
        $column = self::RECEIVED_COLUMNS[$receipt->shop_slug] ?? null;

        DB::transaction(function () use ($receipt, $column): void {
            $locked = BoxReceipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($column !== null && $locked->qty > 0) {
                $item = LotItem::query()->whereKey($locked->lot_item_id)->lockForUpdate()->first();
                if ($item) {
                    LotItem::query()->whereKey($item->id)->update([
                        $column => max(0, (int) $item->{$column} - (int) $locked->qty),
                    ]);
                }
            }
            $locked->delete();
        });

        return response()->json([], 204);
    }

    public function destroyLot(string $shop, string $boxNumber, int $lotId): JsonResponse
    {
        $box = $this->findBox($shop, $boxNumber);
        $column = self::RECEIVED_COLUMNS[$box->shop_slug] ?? null;

        DB::transaction(function () use ($box, $lotId, $column): void {
            $receipts = BoxReceipt::query()
                ->where('shop_slug', $box->shop_slug)
                ->where('box_number', $box->box_number)
                ->where('lot_id', $lotId)
                ->lockForUpdate()
                ->get();

            foreach ($receipts as $receipt) {
                if ($column !== null && $receipt->qty > 0) {
                    $item = LotItem::query()->whereKey($receipt->lot_item_id)->lockForUpdate()->first();
                    if ($item) {
                        LotItem::query()->whereKey($item->id)->update([
                            $column => max(0, (int) $item->{$column} - (int) $receipt->qty),
                        ]);
                    }
                }
                $receipt->delete();
            }
        });

        return response()->json([], 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function boxPayload(Box $box): array
    {
        $receipts = BoxReceipt::query()
            ->where('shop_slug', $box->shop_slug)
            ->where('box_number', $box->box_number);

        return [
            'id' => $box->id,
            'shop' => $box->shop_slug,
            'shop_name' => $box->shopName(),
            'box_number' => $box->box_number,
            'status' => $box->status,
            'qty' => (int) (clone $receipts)->sum('qty'),
            'different_lots' => (int) (clone $receipts)->distinct('lot_id')->count('lot_id'),
        ];
    }

    private function findBox(string $shop, string $boxNumber): Box
    {
        Shop::query()->where('slug', $shop)->firstOrFail();
        $box = Box::findByShopAndNumber($shop, $boxNumber);
        if ($box === null) {
            abort(404, 'Короб не найден.');
        }

        return $box;
    }
}
