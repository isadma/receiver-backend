<?php

namespace App\Http\Controllers;

use App\Models\BoxReceipt;
use App\Models\LotItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

        return view('products.index', [
            'rows' => BoxReceipt::query()->count(),
            'boxes' => BoxReceipt::query()->select('shop_slug', 'box_number')->distinct()->get()->count(),
            'amazon' => (int) ($totals->amazon ?? 0),
            'noon' => (int) ($totals->noon ?? 0),
            'namshi' => (int) ($totals->namshi ?? 0),
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
            ->filterColumn('shop', fn ($query, $keyword) => $query->where('box_receipts.shop_slug', 'like', $this->like($keyword)))
            ->filterColumn('box_number', fn ($query, $keyword) => $query->where('box_receipts.box_number', 'like', $this->like($keyword)))
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
