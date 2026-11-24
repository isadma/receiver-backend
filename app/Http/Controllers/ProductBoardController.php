<?php

namespace App\Http\Controllers;

use App\Models\BoxReceipt;
use App\Models\LotItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class ProductBoardController extends Controller
{
    public function index(): View
    {
        $totals = LotItem::query()
            ->selectRaw('COALESCE(SUM(amazon_received_qty), 0) as amazon')
            ->selectRaw('COALESCE(SUM(noon_received_qty), 0) as noon')
            ->selectRaw('COALESCE(SUM(namshi_received_qty), 0) as namshi')
            ->first();
        $lotScans = $this->lotScans();

        return view('products.index', [
            'rows' => BoxReceipt::query()->count(),
            'boxes' => BoxReceipt::query()->select('shop_slug', 'box_number')->distinct()->get()->count(),
            'qty' => (int) $lotScans->sum('items'),
            'differentLots' => $lotScans->pluck('lot_id')->unique()->count(),
            'scannedLots' => (int) round((float) $lotScans->sum('scans')),
            'amazon' => (int) ($totals->amazon ?? 0),
            'noon' => (int) ($totals->noon ?? 0),
            'namshi' => (int) ($totals->namshi ?? 0),
            'boxStats' => $this->boxStats($lotScans),
        ]);
    }

    public function datatable(): JsonResponse
    {
        $query = BoxReceipt::query()
            ->join('lot_items', 'lot_items.id', '=', 'box_receipts.lot_item_id')
            ->join('lots', 'lots.id', '=', 'box_receipts.lot_id')
            ->select([
                'box_receipts.id',
                'box_receipts.shop_slug',
                'box_receipts.box_number',
                'box_receipts.qty',
                'lots.model',
                'lots.name_ru',
                'lots.name_en',
                'lots.color_en',
                'lots.color',
                'lot_items.size_code',
                'lots.lot_barcode',
                'lot_items.barcode',
            ]);

        return DataTables::of($query)
            ->addColumn('shop', fn ($row) => match ($row->shop_slug) {
                'amazon' => 'Amazon',
                'noon' => 'Noon',
                'namshi' => 'Namshi',
                default => $row->shop_slug,
            })
            ->addColumn('name', fn ($row) => $row->name_ru ?: $row->name_en)
            ->addColumn('color', fn ($row) => $row->color_en ?: $row->color)
            ->filterColumn('shop', function ($query, $keyword): void {
                $slug = match (mb_strtolower(trim($keyword))) {
                    'amazon' => 'amazon',
                    'noon' => 'noon',
                    'namshi' => 'namshi',
                    default => trim($keyword),
                };
                $query->where('box_receipts.shop_slug', $slug);
            })
            ->filterColumn('box_number', fn ($query, $keyword) => $query->where('box_receipts.box_number', trim($keyword)))
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
            ->orderColumn('shop', 'box_receipts.shop_slug $1')
            ->orderColumn('box_number', 'box_receipts.box_number $1')
            ->orderColumn('name', 'lots.name_ru $1')
            ->orderColumn('color', 'lots.color_en $1')
            ->orderColumn('model', 'lots.model $1')
            ->orderColumn('lot_barcode', 'lots.lot_barcode $1')
            ->toJson();
    }

    public function export(): StreamedResponse
    {
        $filename = 'receiver-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Приёмка');

            $sheet->fromArray([
                'Магазин',
                'Короб',
                'Модель',
                'Название',
                'Цвет',
                'Размер',
                'Лот-штрихкод',
                'Штрихкод',
                'Кол-во',
            ], null, 'A1');

            $row = 2;
            foreach ($this->receipts() as $receipt) {
                $lot = $receipt->lot;
                $item = $receipt->item;

                $sheet->fromArray([[
                    $receipt->shopName(),
                    $receipt->box_number,
                    $lot?->model,
                    $lot?->name_ru ?: $lot?->name_en,
                    $lot?->color_en ?: $lot?->color,
                    $item?->size_code,
                    null,
                    null,
                    $receipt->qty,
                ]], null, 'A'.$row);

                $sheet->setCellValueExplicit('G'.$row, (string) ($lot?->lot_barcode ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('H'.$row, (string) ($item?->barcode ?? ''), DataType::TYPE_STRING);
                $row++;
            }

            foreach (range('A', 'I') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * One row per shop + box + lot, with piece qty and how many times that lot was scanned.
     *
     * @return Collection<int, object{shop_slug: string, box_number: string, lot_id: int, items: int|float|string, scans: int|float|string|null}>
     */
    private function lotScans(): Collection
    {
        return BoxReceipt::query()
            ->join('lot_items', 'lot_items.id', '=', 'box_receipts.lot_item_id')
            ->select('box_receipts.shop_slug', 'box_receipts.box_number', 'box_receipts.lot_id')
            ->selectRaw('COALESCE(SUM(box_receipts.qty), 0) as items')
            ->selectRaw('MAX(box_receipts.qty / NULLIF(lot_items.pieces_per_lot, 0)) as scans')
            ->groupBy('box_receipts.shop_slug', 'box_receipts.box_number', 'box_receipts.lot_id')
            ->get();
    }

    /**
     * @param  Collection<int, object{shop_slug: string, box_number: string, lot_id: int, items: int|float|string, scans: int|float|string|null}>  $lotScans
     * @return Collection<int, array{shop: string, shop_slug: string, box: string, items: int, different_lots: int, scanned_lots: int}>
     */
    private function boxStats(Collection $lotScans): Collection
    {
        return $lotScans
            ->groupBy(fn ($row) => $row->shop_slug.'|'.$row->box_number)
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'shop' => match ($first->shop_slug) {
                        'amazon' => 'Amazon',
                        'noon' => 'Noon',
                        'namshi' => 'Namshi',
                        default => $first->shop_slug,
                    },
                    'shop_slug' => $first->shop_slug,
                    'box' => $first->box_number,
                    'items' => (int) $rows->sum('items'),
                    'different_lots' => $rows->count(),
                    'scanned_lots' => (int) round((float) $rows->sum('scans')),
                ];
            })
            ->sortBy(fn (array $row): string => $row['shop_slug']."\t".$row['box'])
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, BoxReceipt>
     */
    private function receipts()
    {
        return BoxReceipt::query()
            ->with(['lot', 'item'])
            ->orderBy('shop_slug')
            ->orderBy('box_number')
            ->orderBy('id')
            ->get();
    }

    private function like(string $keyword): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $keyword).'%';
    }
}
