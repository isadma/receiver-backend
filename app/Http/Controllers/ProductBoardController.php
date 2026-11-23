<?php

namespace App\Http\Controllers;

use App\Models\LotItem;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductBoardController extends Controller
{
    public function index(): View
    {
        $items = $this->items();

        return view('products.index', [
            'items' => $items,
            'amazon' => $items->sum('amazon_received_qty'),
            'noon' => $items->sum('noon_received_qty'),
            'namshi' => $items->sum('namshi_received_qty'),
        ]);
    }

    public function export(): StreamedResponse
    {
        $filename = 'receiver-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'FaturaTarih',
                'Grup',
                'Marka',
                'Model No',
                'Asorti',
                'BedenSeti',
                'Kumaş İngilizce',
                'İngilizce Tanım',
                'Rusca Tanım',
                'LotAdeti',
                'Renk',
                'İngilizce Renk',
                'Adet',
                'Lot Barkod',
                'Barkod',
                'Varyant',
                'BedenKodu',
                'USD',
                'Döviz',
                'amazon_received_qty',
                'noon_received_qty',
                'namshi_received_qty',
            ]);

            foreach ($this->items() as $item) {
                $lot = $item->lot;
                fputcsv($handle, [
                    $lot?->invoice_date,
                    $lot?->group_name,
                    $lot?->brand,
                    $lot?->model,
                    $lot?->assortment,
                    $lot?->size_set,
                    $lot?->fabric_en,
                    $lot?->name_en,
                    $lot?->name_ru,
                    $lot?->invoice_lot_qty,
                    $lot?->color,
                    $lot?->color_en,
                    $item->invoice_qty,
                    $lot?->lot_barcode,
                    $item->barcode,
                    $lot?->variant,
                    $item->size_code,
                    $lot?->usd,
                    $lot?->currency,
                    $item->amazon_received_qty,
                    $item->noon_received_qty,
                    $item->namshi_received_qty,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, LotItem>
     */
    private function items()
    {
        return LotItem::query()
            ->with('lot')
            ->orderBy('id')
            ->get();
    }
}
