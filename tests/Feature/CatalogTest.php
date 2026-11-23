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
        $this->getJson('/api/stats')->assertUnauthorized();
        $this->withBasicAuth('admin', 'wrong')->getJson('/api/stats')->assertUnauthorized();
        $this->get('/')->assertUnauthorized();
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
        ])->assertOk();

        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('noon_received_qty'));
        $this->assertSame(2, (int) LotItem::query()->where('barcode', '8684000000002')->value('noon_received_qty'));
    }

    public function test_unknown_lot_is_not_found(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVMISSING/receive', [
            'shop' => 'amazon',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Lot not found.');
    }

    public function test_receive_rejects_invalid_shop(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'trendyol',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shop']);
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
            ->assertSee('KVTEST01')
            ->assertSee('AR3697-S')
            ->assertSee('Download CSV');
    }

    public function test_csv_export_includes_shop_received_columns(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', ['shop' => 'namshi']);

        $csv = $this->asAdmin()->get('/export.csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('amazon_received_qty', $csv);
        $this->assertStringContainsString('noon_received_qty', $csv);
        $this->assertStringContainsString('namshi_received_qty', $csv);
        $this->assertStringContainsString('KVTEST01', $csv);
        $this->assertStringContainsString('8684000000001', $csv);
        $this->assertStringContainsString('namshi_received_qty', $csv);

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
}
