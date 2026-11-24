<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\BoxReceipt;
use App\Models\LotItem;
use App\Support\CatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CatalogImporter::import(base_path('tests/Fixtures/catalog.csv'));
    }

    public function test_creating_a_box_then_receiving_into_it(): void
    {
        $this->asAdmin()->postJson('/api/boxes', [
            'shop' => 'amazon',
            'box' => '1024',
        ])
            ->assertCreated()
            ->assertJsonPath('data.shop', 'amazon')
            ->assertJsonPath('data.box_number', '1024')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.exists', false);

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1024',
        ])->assertOk();

        $this->asAdmin()->getJson('/api/boxes?shop=amazon')
            ->assertOk()
            ->assertJsonPath('data.0.box_number', '1024')
            ->assertJsonPath('data.0.qty', 7)
            ->assertJsonPath('data.0.different_lots', 1);
    }

    public function test_creating_an_existing_box_returns_exists(): void
    {
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'amazon', 'box' => '1'])->assertCreated();
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'amazon', 'box' => '1'])
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.box_number', '1');
    }

    public function test_receive_fails_when_box_is_missing(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '99',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала выберите короб.');
    }

    public function test_receive_fails_when_box_is_closed(): void
    {
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'amazon', 'box' => '1'])->assertCreated();
        $this->asAdmin()->postJson('/api/boxes/amazon/1/close')
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Этот короб закрыт.');

        $this->asAdmin()->postJson('/api/boxes/amazon/1/open')
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();
    }

    public function test_move_shop_moves_receipts_and_qty(): void
    {
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'namshi', 'box' => '1024'])->assertCreated();
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'namshi',
            'box' => '1024',
        ])->assertOk();

        $this->asAdmin()->postJson('/api/boxes/move-shop', [
            'from_shop' => 'namshi',
            'to_shop' => 'noon',
            'box' => '1024',
        ])
            ->assertOk()
            ->assertJsonPath('data.shop', 'noon')
            ->assertJsonPath('data.box_number', '1024')
            ->assertJsonPath('data.qty', 7);

        $this->assertSame(0, Box::query()->where('shop_slug', 'namshi')->where('box_number', '1024')->count());
        $this->assertSame(0, (int) LotItem::query()->where('barcode', '8684000000001')->value('namshi_received_qty'));
        $this->assertSame(1, (int) LotItem::query()->where('barcode', '8684000000001')->value('noon_received_qty'));
        $this->assertSame(0, BoxReceipt::query()->where('shop_slug', 'namshi')->count());
        $this->assertSame(4, BoxReceipt::query()->where('shop_slug', 'noon')->where('box_number', '1024')->count());
    }

    public function test_move_shop_merges_when_target_already_has_the_box(): void
    {
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'namshi', 'box' => '1024'])->assertCreated();
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'noon', 'box' => '1024'])->assertCreated();
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'namshi',
            'box' => '1024',
        ])->assertOk();
        $this->asAdmin()->postJson('/api/items/8684000000001/receive', [
            'shop' => 'noon',
            'box' => '1024',
        ])->assertOk();

        $this->asAdmin()->postJson('/api/boxes/move-shop', [
            'from_shop' => 'namshi',
            'to_shop' => 'noon',
            'box' => '1024',
        ])
            ->assertOk()
            ->assertJsonPath('data.shop', 'noon')
            ->assertJsonPath('data.box_number', '1024');

        $this->assertSame(0, Box::query()->where('shop_slug', 'namshi')->where('box_number', '1024')->count());
        $this->assertSame(1, Box::query()->where('shop_slug', 'noon')->where('box_number', '1024')->count());
        $this->assertSame(0, (int) LotItem::query()->where('barcode', '8684000000001')->value('namshi_received_qty'));
        $this->assertSame(2, (int) LotItem::query()->where('barcode', '8684000000001')->value('noon_received_qty'));
        $this->assertSame(0, BoxReceipt::query()->where('shop_slug', 'namshi')->count());
        $this->assertSame(4, BoxReceipt::query()->where('shop_slug', 'noon')->where('box_number', '1024')->count());
        $this->assertSame(
            2,
            (int) BoxReceipt::query()
                ->where('shop_slug', 'noon')
                ->where('box_number', '1024')
                ->whereHas('item', fn ($query) => $query->where('barcode', '8684000000001'))
                ->value('qty'),
        );
    }

    public function test_update_receipt_qty_and_delete_barcode(): void
    {
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'amazon', 'box' => '1'])->assertCreated();
        $this->asAdmin()->postJson('/api/items/8684000000001/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();
        $this->asAdmin()->postJson('/api/items/8684000000001/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $receipt = BoxReceipt::query()->first();
        $this->assertNotNull($receipt);
        $this->assertSame(2, (int) $receipt->qty);

        $this->asAdmin()->patchJson('/api/box-receipts/'.$receipt->id, ['qty' => 5])
            ->assertOk()
            ->assertJsonPath('data.qty', 5);
        $this->assertSame(5, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));

        $this->asAdmin()->deleteJson('/api/box-receipts/'.$receipt->id)->assertNoContent();
        $this->assertSame(0, BoxReceipt::query()->count());
        $this->assertSame(0, (int) LotItem::query()->where('barcode', '8684000000001')->value('amazon_received_qty'));
    }

    public function test_delete_lot_from_box(): void
    {
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'amazon', 'box' => '1'])->assertCreated();
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $lotId = BoxReceipt::query()->value('lot_id');
        $this->asAdmin()->deleteJson('/api/boxes/amazon/1/lots/'.$lotId)->assertNoContent();
        $this->assertSame(0, BoxReceipt::query()->count());
        $this->assertSame(0, (int) LotItem::query()->sum('amazon_received_qty'));
    }

    public function test_box_contents_are_grouped_by_lot(): void
    {
        $this->asAdmin()->postJson('/api/boxes', ['shop' => 'amazon', 'box' => '1'])->assertCreated();
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $this->asAdmin()->getJson('/api/boxes/amazon/1')
            ->assertOk()
            ->assertJsonPath('data.box_number', '1')
            ->assertJsonPath('data.lots.0.lot_barcode', 'KVTEST01')
            ->assertJsonPath('data.lots.0.items.0.barcode', '8684000000001');
    }
}
