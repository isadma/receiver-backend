<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveProductRequest;
use App\Http\Requests\UpdateProductBarcodeRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * @var list<string>
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
            'status' => ['sometimes', 'in:mapped,pending'],
        ]);

        $products = Product::query()->orderBy('id');

        $query = trim((string) ($validated['q'] ?? ''));
        if ($query !== '') {
            $this->applySearch($products, $query);
        }

        $this->applyStatus($products, $validated['status'] ?? null);

        return ProductResource::collection(
            $products->paginate($validated['per_page'] ?? 15)->withQueryString()
        );
    }

    public function stats(): JsonResponse
    {
        $totals = Product::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN barcode IS NOT NULL AND barcode != '' THEN 1 ELSE 0 END) as mapped")
            ->selectRaw('COALESCE(SUM(total_expected_qty), 0) as expected_qty')
            ->selectRaw('COALESCE(SUM(total_received_qty), 0) as received_qty')
            ->selectRaw('COALESCE(SUM(amazon_expected_qty), 0) as amazon_expected_qty')
            ->selectRaw('COALESCE(SUM(amazon_received_qty), 0) as amazon_received_qty')
            ->selectRaw('COALESCE(SUM(noon_expected_qty), 0) as noon_expected_qty')
            ->selectRaw('COALESCE(SUM(noon_received_qty), 0) as noon_received_qty')
            ->selectRaw('COALESCE(SUM(namshi_expected_qty), 0) as namshi_expected_qty')
            ->selectRaw('COALESCE(SUM(namshi_received_qty), 0) as namshi_received_qty')
            ->first();

        $total = (int) ($totals->total ?? 0);
        $mapped = (int) ($totals->mapped ?? 0);

        return response()->json([
            'data' => [
                'total' => $total,
                'mapped' => $mapped,
                'pending' => $total - $mapped,
                ...$this->qtySummary((int) $totals->expected_qty, (int) $totals->received_qty),
                'shops' => [
                    'amazon' => $this->qtySummary((int) $totals->amazon_expected_qty, (int) $totals->amazon_received_qty),
                    'noon' => $this->qtySummary((int) $totals->noon_expected_qty, (int) $totals->noon_received_qty),
                    'namshi' => $this->qtySummary((int) $totals->namshi_expected_qty, (int) $totals->namshi_received_qty),
                ],
            ],
        ]);
    }

    public function showBySku(string $sku): ProductResource
    {
        return new ProductResource($this->findBy('sku', $sku));
    }

    public function showByBarcode(string $barcode): ProductResource
    {
        return new ProductResource($this->findBy('barcode', $barcode));
    }

    public function updateBarcode(UpdateProductBarcodeRequest $request, string $sku): ProductResource
    {
        $product = $this->findBy('sku', $sku);
        $barcode = $request->validated('barcode');

        $owner = Product::query()
            ->where('barcode', $barcode)
            ->orderBy('id')
            ->first();

        if ($owner !== null && $owner->id !== $product->id) {
            $ownerSku = $owner->sku ?: "#{$owner->id}";

            throw ValidationException::withMessages([
                'barcode' => "Barcode already mapped to SKU {$ownerSku}.",
            ]);
        }

        $product->update([
            'barcode' => $barcode,
        ]);

        return new ProductResource($product);
    }

    public function receive(ReceiveProductRequest $request, string $barcode): ProductResource
    {
        $product = $this->findBy('barcode', $barcode);
        $shop = Shop::query()->where('slug', $request->validated('shop'))->firstOrFail();
        $qty = (int) $request->validated('qty');
        $shopColumn = self::RECEIVED_COLUMNS[$shop->slug] ?? null;

        if ($shopColumn === null) {
            throw ValidationException::withMessages([
                'shop' => 'This shop cannot receive products.',
            ]);
        }

        DB::transaction(function () use ($product, $shopColumn, $qty): void {
            Product::query()->whereKey($product->id)->increment($shopColumn, $qty);
            Product::query()->whereKey($product->id)->increment('total_received_qty', $qty);
        });

        return new ProductResource($product->refresh());
    }

    private function findBy(string $column, string $value): Product
    {
        $product = Product::query()
            ->where($column, $value)
            ->orderBy('id')
            ->first();

        if ($product === null) {
            abort(404, 'Product not found.');
        }

        return $product;
    }

    private function applySearch(Builder $products, string $query): void
    {
        $pattern = $this->likeContains(mb_strtolower($query));

        $products->where(function ($builder) use ($pattern): void {
            $builder->whereRaw("LOWER(sku) LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    private function applyStatus(Builder $products, ?string $status): void
    {
        if ($status === 'mapped') {
            $products->whereNotNull('barcode')->where('barcode', '!=', '');

            return;
        }

        if ($status === 'pending') {
            $products->where(function ($builder): void {
                $builder->whereNull('barcode')->orWhere('barcode', '');
            });
        }
    }

    private function likeContains(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }

    /**
     * @return array{expected_qty: int, received_qty: int, remaining_qty: int}
     */
    private function qtySummary(int $expected, int $received): array
    {
        return [
            'expected_qty' => $expected,
            'received_qty' => $received,
            'remaining_qty' => max(0, $expected - $received),
        ];
    }
}
