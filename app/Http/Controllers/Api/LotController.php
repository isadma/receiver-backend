<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveLotRequest;
use App\Http\Resources\LotResource;
use App\Models\Lot;
use App\Models\LotItem;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LotController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const RECEIVED_COLUMNS = [
        'amazon' => 'amazon_received_qty',
        'noon' => 'noon_received_qty',
        'namshi' => 'namshi_received_qty',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:255'],
        ]);

        $lots = Lot::query()->with('items')->orderBy('id');
        $query = trim((string) ($validated['q'] ?? ''));

        if ($query !== '') {
            $pattern = $this->likeContains(mb_strtolower($query));
            $lots->where(function (Builder $builder) use ($pattern): void {
                $builder->whereRaw("LOWER(lot_barcode) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(model) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(name_en) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(name_ru) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(color_en) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return LotResource::collection(
            $lots->paginate($validated['per_page'] ?? 20)->withQueryString()
        );
    }

    public function stats(): JsonResponse
    {
        $totals = LotItem::query()
            ->selectRaw('COALESCE(SUM(amazon_received_qty), 0) as amazon_received_qty')
            ->selectRaw('COALESCE(SUM(noon_received_qty), 0) as noon_received_qty')
            ->selectRaw('COALESCE(SUM(namshi_received_qty), 0) as namshi_received_qty')
            ->first();

        $amazon = (int) ($totals->amazon_received_qty ?? 0);
        $noon = (int) ($totals->noon_received_qty ?? 0);
        $namshi = (int) ($totals->namshi_received_qty ?? 0);

        return response()->json([
            'data' => [
                'lots' => Lot::query()->count(),
                'items' => LotItem::query()->count(),
                'received_qty' => $amazon + $noon + $namshi,
                'shops' => [
                    'amazon' => ['received_qty' => $amazon],
                    'noon' => ['received_qty' => $noon],
                    'namshi' => ['received_qty' => $namshi],
                ],
            ],
        ]);
    }

    public function showByBarcode(string $lotBarcode): LotResource
    {
        return new LotResource($this->findLot($lotBarcode)->load('items'));
    }

    public function receive(ReceiveLotRequest $request, string $lotBarcode): JsonResponse
    {
        $lot = $this->findLot($lotBarcode)->load('items');
        $shop = Shop::query()->where('slug', $request->validated('shop'))->firstOrFail();
        $column = self::RECEIVED_COLUMNS[$shop->slug] ?? null;

        if ($column === null) {
            throw ValidationException::withMessages([
                'shop' => 'Этот магазин нельзя использовать для приёмки.',
            ]);
        }

        if ($lot->items->isEmpty()) {
            abort(422, 'У этого лота нет штрихкодов размеров.');
        }

        $allowRepeat = $request->boolean('allow_repeat');

        DB::transaction(function () use ($lot, $column, $allowRepeat): void {
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            $locked->load('items');

            $alreadyReceived = $locked->items->sum(
                fn (LotItem $item): int => $item->totalReceived()
            ) > 0;

            if ($alreadyReceived && ! $allowRepeat) {
                abort(409, 'Этот лот уже принят.');
            }

            foreach ($locked->items as $item) {
                LotItem::query()->whereKey($item->id)->increment($column, $item->pieces_per_lot);
            }
        });

        $lot->refresh()->load('items');
        $added = $lot->items->sum('pieces_per_lot');

        return response()->json([
            'data' => [
                ... (new LotResource($lot))->resolve(),
                'shop' => $shop->slug,
                'shop_name' => $shop->name,
                'pieces_added' => $added,
                'items' => $lot->items->map(fn (LotItem $item): array => [
                    'barcode' => $item->barcode,
                    'size_code' => $item->size_code,
                    'pieces_per_lot' => $item->pieces_per_lot,
                    'pieces_added' => $item->pieces_per_lot,
                    'amazon_received_qty' => $item->amazon_received_qty,
                    'noon_received_qty' => $item->noon_received_qty,
                    'namshi_received_qty' => $item->namshi_received_qty,
                    'total_received_qty' => $item->totalReceived(),
                ])->values(),
            ],
        ]);
    }

    private function findLot(string $lotBarcode): Lot
    {
        $normalized = strtoupper(trim($lotBarcode));
        $lot = $normalized === ''
            ? null
            : Lot::query()->whereRaw('UPPER(TRIM(lot_barcode)) = ?', [$normalized])->first();

        if ($lot === null) {
            abort(404, 'Лот не найден.');
        }

        return $lot;
    }

    private function likeContains(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }
}
