<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductBoardController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()->orderBy('id')->get();

        $totals = [
            'amazon_expected_qty' => $products->sum('amazon_expected_qty'),
            'amazon_received_qty' => $products->sum('amazon_received_qty'),
            'noon_expected_qty' => $products->sum('noon_expected_qty'),
            'noon_received_qty' => $products->sum('noon_received_qty'),
            'namshi_expected_qty' => $products->sum('namshi_expected_qty'),
            'namshi_received_qty' => $products->sum('namshi_received_qty'),
            'total_expected_qty' => $products->sum('total_expected_qty'),
            'total_received_qty' => $products->sum('total_received_qty'),
        ];

        return view('products.index', [
            'products' => $products,
            'totals' => $totals,
            'mapped' => $products->whereNotNull('barcode')->where('barcode', '!=', '')->count(),
            'pending' => $products->filter(
                fn (Product $product): bool => blank($product->barcode)
            )->count(),
        ]);
    }
}
