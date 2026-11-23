<?php

namespace App\Http\Controllers;

use App\Models\LotItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class ProductBoardController extends Controller
{
    public function index(): View
    {
        $totals = LotItem::query()
            ->selectRaw('COUNT(*) as rows')
            ->selectRaw('COALESCE(SUM(amazon_received_qty), 0) as amazon')
            ->selectRaw('COALESCE(SUM(noon_received_qty), 0) as noon')
            ->selectRaw('COALESCE(SUM(namshi_received_qty), 0) as namshi')
            ->first();

        return view('products.index', [
            'rows' => (int) ($totals->rows ?? 0),
            'amazon' => (int) ($totals->amazon ?? 0),
            'noon' => (int) ($totals->noon ?? 0),
            'namshi' => (int) ($totals->namshi ?? 0),
        ]);
    }

    public function datatable(): JsonResponse
    {
        $query = LotItem::query()
            ->join('lots', 'lots.id', '=', 'lot_items.lot_id')
            ->select([
                'lot_items.id',
                'lots.model',
                'lots.name_ru',
                'lots.name_en',
                'lots.color_en',
                'lots.color',
                'lot_items.size_code',
                'lots.lot_barcode',
                'lot_items.barcode',
                'lot_items.pieces_per_lot',
                'lot_items.amazon_received_qty',
                'lot_items.noon_received_qty',
                'lot_items.namshi_received_qty',
            ]);

        return DataTables::of($query)
            ->addColumn('name', fn ($row) => $row->name_ru ?: $row->name_en)
            ->addColumn('color', fn ($row) => $row->color_en ?: $row->color)
            ->filterColumn('name', function ($query, $keyword): void {
                $like = $this->like($keyword);
                $query->where(function ($builder) use ($like): void {
                    $builder->where('lots.name_ru', 'like', $like)
                        ->orWhere('lots.name_en', 'like', $like);
                });
            })
            ->filterColumn('color', function ($query, $keyword): void {
                $like = $this->like($keyword);
                $query->where(function ($builder) use ($like): void {
                    $builder->where('lots.color_en', 'like', $like)
                        ->orWhere('lots.color', 'like', $like);
                });
            })
            ->filterColumn('model', fn ($query, $keyword) => $query->where('lots.model', 'like', $this->like($keyword)))
            ->filterColumn('lot_barcode', fn ($query, $keyword) => $query->where('lots.lot_barcode', 'like', $this->like($keyword)))
            ->filterColumn('size_code', fn ($query, $keyword) => $query->where('lot_items.size_code', 'like', $this->like($keyword)))
            ->filterColumn('barcode', fn ($query, $keyword) => $query->where('lot_items.barcode', 'like', $this->like($keyword)))
            ->orderColumn('name', 'lots.name_ru $1')
            ->orderColumn('color', 'lots.color_en $1')
            ->orderColumn('model', 'lots.model $1')
            ->orderColumn('lot_barcode', 'lots.lot_barcode $1')
            ->toJson();
    }

    public function export(): StreamedResponse
    {
        $filename = 'receiver-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $this->writeCsvRow($handle, [
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
                $this->writeCsvRow($handle, [
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

    /**
     * @param  resource  $handle
     * @param  list<mixed>  $fields
     */
    private function writeCsvRow($handle, array $fields): void
    {
        fputcsv($handle, array_map(fn ($value) => $this->csvValue($value), $fields), ',', '"', '\\', "\r\n");
    }

    private function csvValue(mixed $value): string
    {
        $text = (string) ($value ?? '');

        if ($text !== '' && ! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8,Windows-1251,Windows-1252,ISO-8859-1');
        }

        return $text;
    }

    private function like(string $keyword): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword).'%';
    }
}
