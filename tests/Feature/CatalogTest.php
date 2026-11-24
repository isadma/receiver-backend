<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\BoxReceipt;
use App\Models\Lot;
use App\Models\LotItem;
use App\Support\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CatalogImporter::import(base_path('tests/Fixtures/catalog.csv'));

        foreach (['amazon', 'noon', 'namshi'] as $shop) {
            foreach (['1', '2', 'A-12'] as $number) {
                Box::query()->firstOrCreate(
                    ['shop_slug' => $shop, 'box_number' => $number],
                    ['status' => Box::STATUS_OPEN],
                );
            }
        }
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/stats')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Не авторизован.');
        $this->withBasicAuth('admin', 'wrong')->getJson('/api/stats')->assertUnauthorized();
        $this->get('/')->assertUnauthorized();
        $this->get('/datatable')->assertUnauthorized();
        $this->get('/export.xlsx')->assertUnauthorized();
    }

    public function test_import_repairs_shifted_rows_and_sets_pieces_per_lot(): void
    {
        $this->assertSame(2, Lot::query()->count());
        $this->assertSame(8, LotItem::query()->count());

        $lot = Lot::query()->where('lot_barcode', 'KVTEST01')->first();
        $this->assertNotNull($lot);
        $this->assertSame('AR3697-S', $lot->model);
        $this->assertSame(1, (int) $lot->items()->where('size_code', 'S')->value('pieces_per_lot'));
        $this->assertSame(2, (int) $lot->items()->where('size_code', 'M')->value('pieces_per_lot'));
        $this->assertSame(2, (int) $lot->items()->where('size_code', 'XL')->value('pieces_per_lot'));

        $shifted = Lot::query()->where('lot_barcode', 'KVSHIFT1')->first();
        $this->assertNotNull($shifted);
        $this->assertSame('US2426-4', $shifted->model);
        $this->assertSame('NAVY', $shifted->color_en);
        $this->assertTrue($shifted->items()->where('size_code', '7-8')->where('barcode', '8684000000101')->exists());
        $this->assertTrue($shifted->items()->where('size_code', '4-5')->exists());
    }

    public function test_receive_adds_one_polybag_to_the_selected_shop(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('data.lot_barcode', 'KVTEST01')
            ->assertJsonPath('data.shop', 'amazon')
            ->assertJsonPath('data.box', '1')
            ->assertJsonPath('data.pieces_added', 7)
            ->assertJsonPath('data.items.0.size_code', 'S')
            ->assertJsonPath('data.items.0.pieces_added', 1)
            ->assertJsonPath('data.items.1.pieces_added', 2);

        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
        $this->assertSame(2, (int) LotItem::query()->where('barcode', '8684000000002')->value('amazon_received_qty'));
        $this->assertSame(0, (int) LotItem::query()->where('barcode', '8684000000001')->value('noon_received_qty'));

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
            'box' => '1',
            'allow_repeat' => true,
        ])->assertOk();

        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('noon_received_qty'));
        $this->assertSame(2, (int) LotItem::query()->where('barcode', '8684000000002')->value('noon_received_qty'));
    }

    public function test_duplicate_lot_is_rejected_unless_repeat_is_allowed(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Этот лот уже принят.');

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
            'box' => '1',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Этот лот уже принят.');

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
            'box' => '2',
            'allow_repeat' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.shop', 'noon')
            ->assertJsonPath('data.pieces_added', 7);

        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('noon_received_qty'));
    }

    public function test_unknown_lot_is_not_found(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVMISSING/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Лот не найден.');
    }

    public function test_receive_item_requires_a_box_number(): void
    {
        $this->asAdmin()->postJson('/api/items/8684000000001/receive', [
            'shop' => 'amazon',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['box'])
            ->assertJsonPath('errors.box.0', 'Введите номер короба.');
    }

    public function test_unknown_item_barcode_is_not_found(): void
    {
        $this->asAdmin()->postJson('/api/items/0000000000000/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Штрихкод не найден.');
    }

    public function test_receive_item_adds_one_piece_for_that_size_only(): void
    {
        $this->asAdmin()->postJson('/api/items/8684000000002/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('data.lot_barcode', 'KVTEST01')
            ->assertJsonPath('data.shop', 'amazon')
            ->assertJsonPath('data.box', '1')
            ->assertJsonPath('data.pieces_added', 1)
            ->assertJsonPath('data.items.0.barcode', '8684000000002')
            ->assertJsonPath('data.items.0.size_code', 'M')
            ->assertJsonPath('data.items.0.pieces_added', 1)
            ->assertJsonCount(1, 'data.items');

        $this->assertSame(0, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000002')->value('amazon_received_qty'));
        $this->assertSame(1, (int) BoxReceipt::query()
            ->where('shop_slug', 'amazon')
            ->where('box_number', '1')
            ->whereHas('item', fn ($query) => $query->where('barcode', '8684000000002'))
            ->value('qty'));
        $this->assertSame(1, BoxReceipt::query()->count());
    }

    public function test_receive_item_can_scan_the_same_barcode_again(): void
    {
        $this->asAdmin()->postJson('/api/items/8684000000001/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $this->asAdmin()->postJson('/api/items/8684000000001/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('data.pieces_added', 1);

        $this->assertSame(2, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
        $this->assertSame(2, (int) BoxReceipt::query()
            ->where('shop_slug', 'amazon')
            ->where('box_number', '1')
            ->whereHas('item', fn ($query) => $query->where('barcode', '8684000000001'))
            ->value('qty'));
    }

    public function test_receive_item_works_after_the_lot_was_already_received(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $this->asAdmin()->postJson('/api/items/8684000000001/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('data.pieces_added', 1);

        $this->assertSame(2, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
    }

    public function test_receive_finds_lot_barcode_case_insensitively(): void
    {
        $this->asAdmin()->postJson('/api/lots/kvtest01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('data.lot_barcode', 'KVTEST01')
            ->assertJsonPath('data.pieces_added', 7);
    }

    public function test_receive_rejects_invalid_shop(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'trendyol',
            'box' => '1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shop'])
            ->assertJsonPath('errors.shop.0', 'Неизвестный магазин.');
    }

    public function test_catalog_import_loads_invoice_csv(): void
    {
        $this->asAdmin()->postJson('/api/catalog/import')
            ->assertOk()
            ->assertJsonPath('data.lots', 204)
            ->assertJsonPath('data.items', 868);

        $this->assertTrue(Lot::query()->where('lot_barcode', 'KV528264')->exists());
    }

    public function test_stats_count_received_pieces_per_shop(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ]);

        $this->asAdmin()->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.lots', 2)
            ->assertJsonPath('data.items', 8)
            ->assertJsonPath('data.received_qty', 7)
            ->assertJsonPath('data.shops.amazon.received_qty', 7)
            ->assertJsonPath('data.shops.noon.received_qty', 0)
            ->assertJsonPath('data.shops.namshi.received_qty', 0);
    }

    public function test_receive_requires_a_box_number(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['box'])
            ->assertJsonPath('errors.box.0', 'Введите номер короба.');

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '   ',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['box']);
    }

    public function test_receive_writes_a_receipt_per_size(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $this->assertSame(4, BoxReceipt::query()->count());
        $this->assertSame(1, (int) BoxReceipt::query()
            ->where('shop_slug', 'amazon')
            ->where('box_number', '1')
            ->whereHas('item', fn ($query) => $query->where('barcode', '8684000000001'))
            ->value('qty'));
        $this->assertSame(2, (int) BoxReceipt::query()
            ->where('shop_slug', 'amazon')
            ->where('box_number', '1')
            ->whereHas('item', fn ($query) => $query->where('barcode', '8684000000002'))
            ->value('qty'));

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
            'allow_repeat' => true,
        ])->assertOk();

        $this->assertSame(4, BoxReceipt::query()->count());
        $this->assertSame(2, (int) BoxReceipt::query()
            ->where('shop_slug', 'amazon')
            ->where('box_number', '1')
            ->whereHas('item', fn ($query) => $query->where('barcode', '8684000000001'))
            ->value('qty'));
    }

    public function test_same_lot_into_a_second_box_creates_separate_receipts(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => 'A-12',
            'allow_repeat' => true,
        ])->assertOk();

        $this->assertSame(8, BoxReceipt::query()->count());
        $this->assertSame(4, BoxReceipt::query()->where('shop_slug', 'amazon')->where('box_number', '1')->count());
        $this->assertSame(4, BoxReceipt::query()->where('shop_slug', 'amazon')->where('box_number', 'A-12')->count());
        $this->assertSame(1, (int) BoxReceipt::query()
            ->where('box_number', 'A-12')
            ->whereHas('item', fn ($query) => $query->where('barcode', '8684000000001'))
            ->value('qty'));
    }

    public function test_home_shows_catalog_table(): void
    {
        $this->asAdmin()->get('/')
            ->assertOk()
            ->assertSee('Приёмка')
            ->assertSee('Короба')
            ->assertSee('Разных лотов')
            ->assertSee('Лотов всего')
            ->assertSee('По коробам')
            ->assertSee('Пока нет коробов.')
            ->assertSee('Магазин')
            ->assertSee('Короб')
            ->assertSee('Скачать Excel')
            ->assertSee('Все магазины')
            ->assertSee('Все короба')
            ->assertSee('id="filter-shop"', false)
            ->assertSee('id="filter-box"', false)
            ->assertSee('id="catalog"', false)
            ->assertSee('/datatable');
    }

    public function test_home_shows_item_and_lot_counts_per_box(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();
        $this->asAdmin()->postJson('/api/lots/KVSHIFT1/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
            'box' => '1',
            'allow_repeat' => true,
        ])->assertOk();
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
            'allow_repeat' => true,
        ])->assertOk();

        $this->asAdmin()->get('/')
            ->assertOk()
            ->assertDontSee('Пока нет коробов.')
            ->assertSeeInOrder([
                'Штук',
                '25',
                'Разных лотов',
                '2',
                'Лотов всего',
                '4',
                'По коробам',
                'Amazon',
                'Короб 1',
                '18',
                'штук',
                '2',
                'разных лотов',
                '3',
                'лотов всего',
                'Noon',
                'Короб 1',
                '7',
                'штук',
                '1',
                'разных лотов',
                '1',
                'лотов всего',
            ]);
    }

    public function test_datatable_returns_box_receipt_rows(): void
    {
        $this->asAdmin()->getJson('/datatable?'.$this->datatableQuery())
            ->assertOk()
            ->assertJsonPath('recordsTotal', 0);

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $response = $this->asAdmin()->getJson('/datatable?'.$this->datatableQuery('KVTEST01'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 4);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertTrue(collect($data)->contains(fn (array $row): bool => ($row['shop'] ?? null) === 'Amazon'
            && ($row['box_number'] ?? null) === '1'
            && ($row['lot_barcode'] ?? null) === 'KVTEST01'
            && ($row['barcode'] ?? null) === '8684000000001'
            && (int) ($row['qty'] ?? 0) === 1));
        $this->assertTrue(collect($data)->contains(fn (array $row): bool => ($row['model'] ?? null) === 'AR3697-S'));
    }

    public function test_datatable_filters_by_shop_and_box(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();
        $this->asAdmin()->postJson('/api/lots/KVSHIFT1/receive', [
            'shop' => 'amazon',
            'box' => 'A-12',
        ])->assertOk();
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
            'box' => '1',
            'allow_repeat' => true,
        ])->assertOk();

        $amazon = $this->asAdmin()->getJson('/datatable?'.$this->datatableQuery('', [
            'shop' => 'amazon',
        ]))->assertOk();
        $this->assertSame(8, $amazon->json('recordsFiltered'));
        $this->assertTrue(collect($amazon->json('data'))->every(fn (array $row): bool => ($row['shop'] ?? null) === 'Amazon'));

        $amazonBox = $this->asAdmin()->getJson('/datatable?'.$this->datatableQuery('', [
            'shop' => 'amazon',
            'box_number' => '1',
        ]))->assertOk();
        $this->assertSame(4, $amazonBox->json('recordsFiltered'));
        $this->assertTrue(collect($amazonBox->json('data'))->every(
            fn (array $row): bool => ($row['shop'] ?? null) === 'Amazon' && ($row['box_number'] ?? null) === '1'
        ));

        $noonBox = $this->asAdmin()->getJson('/datatable?'.$this->datatableQuery('', [
            'shop' => 'noon',
            'box_number' => '1',
        ]))->assertOk();
        $this->assertSame(4, $noonBox->json('recordsFiltered'));
        $this->assertTrue(collect($noonBox->json('data'))->every(
            fn (array $row): bool => ($row['shop'] ?? null) === 'Noon' && ($row['box_number'] ?? null) === '1'
        ));
    }

    public function test_excel_export_includes_shop_box_barcode_and_qty(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'namshi',
            'box' => '1',
        ]);

        $contents = $this->asAdmin()->get('/export.xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'receiver').'.xlsx';
        file_put_contents($path, $contents);

        try {
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('Магазин', $sheet->getCell('A1')->getValue());
            $this->assertSame('Короб', $sheet->getCell('B1')->getValue());
            $this->assertSame('Штрихкод', $sheet->getCell('H1')->getValue());
            $this->assertSame('Кол-во', $sheet->getCell('I1')->getValue());

            $found = false;
            foreach ($sheet->getRowIterator(2) as $row) {
                $index = $row->getRowIndex();
                if ((string) $sheet->getCell('H'.$index)->getValue() === '8684000000001') {
                    $this->assertSame('Namshi', (string) $sheet->getCell('A'.$index)->getValue());
                    $this->assertSame('1', (string) $sheet->getCell('B'.$index)->getValue());
                    $this->assertSame('KVTEST01', (string) $sheet->getCell('G'.$index)->getValue());
                    $this->assertSame('Бермудская команда', (string) $sheet->getCell('D'.$index)->getValue());
                    $this->assertSame('1', (string) $sheet->getCell('I'.$index)->getValue());
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found);
        } finally {
            @unlink($path);
        }
    }

    public function test_reset_clears_received_qty_and_keeps_catalog(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ]);
        $this->asAdmin()->postJson('/api/demo/reset')->assertOk()->assertJsonPath('data.reset', true);

        $this->assertSame(2, Lot::query()->count());
        $this->assertSame(0, (int) LotItem::query()->sum('amazon_received_qty'));
        $this->assertSame(0, BoxReceipt::query()->count());
        $this->assertTrue(Lot::query()->where('lot_barcode', 'KVTEST01')->exists());
    }

    public function test_api_status_requires_auth(): void
    {
        $this->asAdmin()->getJson('/api')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    /**
     * @param  array<string, string>  $columnSearch
     */
    private function datatableQuery(string $search = '', array $columnSearch = []): string
    {
        $columns = [
            'shop',
            'box_number',
            'model',
            'name',
            'color',
            'size_code',
            'lot_barcode',
            'barcode',
            'qty',
        ];

        $query = [
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => [
                'value' => $search,
                'regex' => 'false',
            ],
            'order' => [
                ['column' => 4, 'dir' => 'asc'],
            ],
        ];

        foreach ($columns as $index => $name) {
            $query['columns'][$index] = [
                'data' => $name,
                'name' => $name,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => [
                    'value' => $columnSearch[$name] ?? '',
                    'regex' => 'false',
                ],
            ];
        }

        return http_build_query($query);
    }
}
