<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_paginated(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 10)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.sku', 'USB2866')
            ->assertJsonPath('data.0.barcode', null)
            ->assertJsonPath('data.0.total_expected_qty', 6)
            ->assertJsonPath('data.0.amazon_expected_qty', 2)
            ->assertJsonPath('data.0.noon_expected_qty', 2)
            ->assertJsonPath('data.0.namshi_expected_qty', 2);

        $this->getJson('/api/products?page=2')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 10);
    }

    public function test_products_respect_per_page(): void
    {
        $this->getJson('/api/products?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_invalid_pagination_is_rejected(): void
    {
        $this->getJson('/api/products?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_product_can_be_found_by_sku(): void
    {
        $this->getJson('/api/products/sku/USB2866')
            ->assertOk()
            ->assertJsonPath('data.sku', 'USB2866')
            ->assertJsonPath('data.name', 'Toddler white T-shirt')
            ->assertJsonPath('data.barcode', null);
    }

    public function test_missing_sku_returns_not_found(): void
    {
        $this->getJson('/api/products/sku/USB0000')
            ->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_barcode_can_be_updated_by_sku(): void
    {
        $response = $this->patchJson('/api/products/USB2866/barcode', [
            'barcode' => ' 5901234123457 ',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sku', 'USB2866')
            ->assertJsonPath('data.barcode', '5901234123457');

        $this->assertSame('5901234123457', Product::query()->where('sku', 'USB2866')->value('barcode'));
    }

    public function test_barcode_update_requires_a_barcode(): void
    {
        $this->patchJson('/api/products/USB2866/barcode', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['barcode']);
    }

    public function test_barcode_update_for_missing_sku_returns_not_found(): void
    {
        $this->patchJson('/api/products/USB0000/barcode', [
            'barcode' => '5901234123457',
        ])->assertNotFound();
    }

    public function test_product_can_be_found_by_barcode(): void
    {
        Product::query()->where('sku', 'USB3102')->update([
            'barcode' => '4006381333931',
        ]);

        $this->getJson('/api/products/barcode/4006381333931')
            ->assertOk()
            ->assertJsonPath('data.sku', 'USB3102')
            ->assertJsonPath('data.name', 'Toddler navy shorts')
            ->assertJsonPath('data.barcode', '4006381333931');
    }

    public function test_missing_barcode_returns_not_found(): void
    {
        $this->getJson('/api/products/barcode/0000000000000')
            ->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_products_can_be_searched_by_sku(): void
    {
        $this->getJson('/api/products?q=usb2866')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'USB2866')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_products_can_be_searched_by_name(): void
    {
        $this->getJson('/api/products?q=navy shorts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'USB3102');
    }

    public function test_search_escapes_like_wildcards(): void
    {
        $this->getJson('/api/products?q=%')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/products?q=_')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_products_can_be_filtered_by_mapped_status(): void
    {
        Product::query()->where('sku', 'USB2866')->update([
            'barcode' => '8684053504184',
        ]);

        $this->getJson('/api/products?status=mapped')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'USB2866')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/products?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 9);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->getJson('/api/products?status=all')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_stats_return_mapped_and_pending_counts(): void
    {
        Product::query()->whereIn('sku', ['USB2866', 'USB3102'])->update([
            'barcode' => '8684053504184',
        ]);
        Product::query()->where('sku', 'USB3102')->update([
            'barcode' => '4006381333931',
        ]);

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 10)
            ->assertJsonPath('data.mapped', 2)
            ->assertJsonPath('data.pending', 8)
            ->assertJsonPath('data.expected_qty', 60)
            ->assertJsonPath('data.received_qty', 0)
            ->assertJsonPath('data.remaining_qty', 60)
            ->assertJsonPath('data.shops.amazon.expected_qty', 20)
            ->assertJsonPath('data.shops.noon.expected_qty', 20)
            ->assertJsonPath('data.shops.namshi.expected_qty', 20)
            ->assertJsonPath('data.shops.amazon.received_qty', 0)
            ->assertJsonPath('data.shops.noon.received_qty', 0)
            ->assertJsonPath('data.shops.namshi.received_qty', 0);
    }

    public function test_shops_are_listed(): void
    {
        $this->getJson('/api/shops')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.slug', 'amazon')
            ->assertJsonPath('data.0.name', 'Amazon')
            ->assertJsonPath('data.1.slug', 'noon')
            ->assertJsonPath('data.2.slug', 'namshi');
    }

    public function test_receive_increments_shop_and_total_qty(): void
    {
        Product::query()->where('sku', 'USB2866')->update([
            'barcode' => '8684053504184',
        ]);

        $this->postJson('/api/products/barcode/8684053504184/receive', [
            'shop' => 'amazon',
            'qty' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('data.sku', 'USB2866')
            ->assertJsonPath('data.amazon_received_qty', 10)
            ->assertJsonPath('data.noon_received_qty', 0)
            ->assertJsonPath('data.total_received_qty', 10);

        $this->postJson('/api/products/barcode/8684053504184/receive', [
            'shop' => 'noon',
        ])
            ->assertOk()
            ->assertJsonPath('data.amazon_received_qty', 10)
            ->assertJsonPath('data.noon_received_qty', 1)
            ->assertJsonPath('data.total_received_qty', 11);

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.received_qty', 11)
            ->assertJsonPath('data.expected_qty', 60)
            ->assertJsonPath('data.remaining_qty', 49)
            ->assertJsonPath('data.shops.amazon.received_qty', 10)
            ->assertJsonPath('data.shops.amazon.expected_qty', 20)
            ->assertJsonPath('data.shops.amazon.remaining_qty', 10)
            ->assertJsonPath('data.shops.noon.received_qty', 1)
            ->assertJsonPath('data.shops.namshi.received_qty', 0);
    }

    public function test_receive_for_unmapped_barcode_returns_not_found(): void
    {
        $this->postJson('/api/products/barcode/0000000000000/receive', [
            'shop' => 'amazon',
            'qty' => 1,
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_receive_rejects_invalid_shop(): void
    {
        Product::query()->where('sku', 'USB2866')->update([
            'barcode' => '8684053504184',
        ]);

        $this->postJson('/api/products/barcode/8684053504184/receive', [
            'shop' => 'trendyol',
            'qty' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shop']);
    }

    public function test_receive_rejects_invalid_qty(): void
    {
        Product::query()->where('sku', 'USB2866')->update([
            'barcode' => '8684053504184',
        ]);

        $this->postJson('/api/products/barcode/8684053504184/receive', [
            'shop' => 'amazon',
            'qty' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['qty']);
    }

    public function test_barcode_cannot_be_mapped_to_a_different_product(): void
    {
        Product::query()->where('sku', 'USB2866')->update([
            'barcode' => '8684053504184',
        ]);

        $this->patchJson('/api/products/USB3102/barcode', [
            'barcode' => '8684053504184',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['barcode'])
            ->assertJsonPath('errors.barcode.0', 'Barcode already mapped to SKU USB2866.');

        $this->assertNull(Product::query()->where('sku', 'USB3102')->value('barcode'));
    }

    public function test_same_product_can_keep_its_barcode(): void
    {
        Product::query()->where('sku', 'USB2866')->update([
            'barcode' => '8684053504184',
        ]);

        $this->patchJson('/api/products/USB2866/barcode', [
            'barcode' => '8684053504184',
        ])
            ->assertOk()
            ->assertJsonPath('data.sku', 'USB2866')
            ->assertJsonPath('data.barcode', '8684053504184');
    }

    public function test_home_shows_product_table(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('USB2866')
            ->assertSee('Toddler white T-shirt')
            ->assertSee('Amazon exp')
            ->assertSee('Noon rec')
            ->assertSee('Namshi exp')
            ->assertSee('Total rec');
    }

    public function test_demo_reset_rebuilds_catalog(): void
    {
        Product::query()->where('sku', 'USB2866')->update([
            'barcode' => '8684053504184',
            'amazon_received_qty' => 2,
            'total_received_qty' => 2,
        ]);
        Product::query()->where('sku', 'USB3102')->delete();
        Product::query()->create([
            'sku' => 'USB9999',
            'name' => 'Temporary product',
            'total_expected_qty' => 6,
        ]);

        $this->postJson('/api/demo/reset')
            ->assertOk()
            ->assertJsonPath('data.reset', true)
            ->assertJsonPath('data.products', 10);

        $this->assertSame(10, Product::query()->count());
        $this->assertTrue(Product::query()->where('sku', 'USB2866')->exists());
        $this->assertTrue(Product::query()->where('sku', 'USB3102')->exists());
        $this->assertFalse(Product::query()->where('sku', 'USB9999')->exists());
        $this->assertNull(Product::query()->where('sku', 'USB2866')->value('barcode'));
        $this->assertSame(0, (int) Product::query()->where('sku', 'USB2866')->value('total_received_qty'));
        $this->assertSame(6, (int) Product::query()->where('sku', 'USB2866')->value('total_expected_qty'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 10)
            ->assertJsonPath('data.mapped', 0)
            ->assertJsonPath('data.pending', 10)
            ->assertJsonPath('data.expected_qty', 60)
            ->assertJsonPath('data.received_qty', 0)
            ->assertJsonPath('data.shops.amazon.expected_qty', 20)
            ->assertJsonPath('data.shops.noon.expected_qty', 20)
            ->assertJsonPath('data.shops.namshi.expected_qty', 20);
    }
}
