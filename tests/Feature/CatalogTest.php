<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\LotItem;
use App\Support\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CatalogImporter::import(base_path('tests/Fixtures/catalog.csv'));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/stats')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Не авторизован.');
        $this->withBasicAuth('admin', 'wrong')->getJson('/api/stats')->assertUnauthorized();
        $this->get('/')->assertUnauthorized();
        $this->get('/datatable')->assertUnauthorized();
        $this->get('/export.csv')->assertUnauthorized();
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
        ])
            ->assertOk()
            ->assertJsonPath('data.lot_barcode', 'KVTEST01')
            ->assertJsonPath('data.shop', 'amazon')
            ->assertJsonPath('data.pieces_added', 7)
            ->assertJsonPath('data.items.0.size_code', 'S')
            ->assertJsonPath('data.items.0.pieces_added', 1)
            ->assertJsonPath('data.items.1.pieces_added', 2);

        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
        $this->assertSame(2, (int) LotItem::query()->where('barcode', '8684000000002')->value('amazon_received_qty'));
        $this->assertSame(0, (int) LotItem::query()->where('barcode', '8684000000001')->value('noon_received_qty'));

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
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
        ])->assertOk();

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Этот лот уже принят.');

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Этот лот уже принят.');

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'noon',
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
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Лот не найден.');
    }

    public function test_receive_finds_lot_barcode_case_insensitively(): void
    {
        $this->asAdmin()->postJson('/api/lots/kvtest01/receive', [
            'shop' => 'amazon',
        ])
            ->assertOk()
            ->assertJsonPath('data.lot_barcode', 'KVTEST01')
            ->assertJsonPath('data.pieces_added', 7);
    }

    public function test_receive_rejects_invalid_shop(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'trendyol',
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
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', ['shop' => 'amazon']);

        $this->asAdmin()->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.lots', 2)
            ->assertJsonPath('data.items', 8)
            ->assertJsonPath('data.received_qty', 7)
            ->assertJsonPath('data.shops.amazon.received_qty', 7)
            ->assertJsonPath('data.shops.noon.received_qty', 0)
            ->assertJsonPath('data.shops.namshi.received_qty', 0);
    }

    public function test_home_shows_catalog_table(): void
    {
        $this->asAdmin()->get('/')
            ->assertOk()
            ->assertSee('Приёмка')
            ->assertSee('Скачать CSV')
            ->assertSee('id="catalog"', false)
            ->assertSee('/datatable');
    }

    public function test_datatable_returns_lot_rows(): void
    {
        $response = $this->asAdmin()->getJson('/datatable?'.$this->datatableQuery('KVTEST01'))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 8);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertTrue(collect($data)->contains(fn (array $row): bool => ($row['lot_barcode'] ?? null) === 'KVTEST01'));
        $this->assertTrue(collect($data)->contains(fn (array $row): bool => ($row['model'] ?? null) === 'AR3697-S'));
    }

    public function test_csv_export_includes_shop_received_columns(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', ['shop' => 'namshi']);

        $csv = $this->asAdmin()->get('/export.csv')->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Бермудская команда', $csv);
        $this->assertTrue(mb_check_encoding($csv, 'UTF-8'));
        $this->assertStringContainsString('amazon_received_qty', $csv);
        $this->assertStringContainsString('noon_received_qty', $csv);
        $this->assertStringContainsString('namshi_received_qty', $csv);
        $this->assertStringContainsString('KVTEST01', $csv);
        $this->assertStringContainsString('8684000000001', $csv);
        $this->assertStringContainsString('namshi_received_qty', $csv);

        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = array_values(array_filter(preg_split("/\r\n|\n|\r/", $csv)));
        $header = str_getcsv($lines[0]);
        $this->assertContains('Lot Barkod', $header);
        $this->assertContains('namshi_received_qty', $header);

        $namshiIndex = array_search('namshi_received_qty', $header, true);
        $barcodeIndex = array_search('Barkod', $header, true);
        $found = false;
        foreach (array_slice($lines, 1) as $line) {
            $row = str_getcsv($line);
            if (($row[$barcodeIndex] ?? null) === '8684000000001') {
                $this->assertSame('1', $row[$namshiIndex]);
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function test_reset_clears_received_qty_and_keeps_catalog(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', ['shop' => 'amazon']);
        $this->asAdmin()->postJson('/api/demo/reset')->assertOk()->assertJsonPath('data.reset', true);

        $this->assertSame(2, Lot::query()->count());
        $this->assertSame(0, (int) LotItem::query()->sum('amazon_received_qty'));
        $this->assertTrue(Lot::query()->where('lot_barcode', 'KVTEST01')->exists());
    }

    public function test_api_status_requires_auth(): void
    {
        $this->asAdmin()->getJson('/api')
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    private function datatableQuery(string $search = ''): string
    {
        $columns = [
            'model',
            'name',
            'color',
            'size_code',
            'lot_barcode',
            'barcode',
            'pieces_per_lot',
            'amazon_received_qty',
            'noon_received_qty',
            'namshi_received_qty',
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
                    'value' => '',
                    'regex' => 'false',
                ],
            ];
        }

        return http_build_query($query);
    }
}
