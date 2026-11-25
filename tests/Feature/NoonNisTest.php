<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\BoxReceipt;
use App\Models\LotItem;
use App\Support\CatalogImporter;
use App\Support\ExcelSheet;
use App\Support\NoonNisExporter;
use App\Support\NoonNisMapper;
use App\Support\NuriCatalogUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class NoonNisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CatalogImporter::import(base_path('tests/Fixtures/catalog.csv'));
        Box::query()->firstOrCreate(
            ['shop_slug' => 'amazon', 'box_number' => '1'],
            ['status' => Box::STATUS_OPEN],
        );
    }

    public function test_nuri_import_skips_extra_barcodes_and_keeps_receipts(): void
    {
        $this->asAdmin()->postJson('/api/lots/KVTEST01/receive', [
            'shop' => 'amazon',
            'box' => '1',
        ])->assertOk();

        $receipts = BoxReceipt::query()->count();
        $this->assertGreaterThan(0, $receipts);

        $nuri = $this->writeNuri([
            ['KVNURI01', '8684053490067', 'US2439-4', 'SAND', '7-8', 'Boys Two Pieces Set'],
            ['KVNURI01', '8684053490067', 'US2439-4', 'SAND', '7-8', 'Boys Two Pieces Set'],
            ['KVNURI99', '9999999999999', 'US9999-4', 'RED', '4-5', 'Boys Two Pieces Set'],
        ]);
        $receiver = $this->writeReceiver([
            ['Amazon', '2001', '8684053490067'],
            ['Noon', '1024', '8684053490067'],
        ]);

        $result = NuriCatalogUpserter::import($nuri, 'Bangladesh', ExcelSheet::barcodesFromReceiver($receiver));

        $this->assertSame(1, $result['items']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($receipts, BoxReceipt::query()->count());
        $this->assertTrue(LotItem::query()->where('barcode', '8684053490067')->exists());
        $this->assertFalse(LotItem::query()->where('barcode', '9999999999999')->exists());
        $this->assertSame('Bangladesh', LotItem::query()->where('barcode', '8684053490067')->first()?->lot?->country_of_origin);
    }

    public function test_nis_export_writes_one_parent_and_one_child_per_receiver_barcode(): void
    {
        $nuri = $this->writeNuri([
            ['KVNURI01', '8684053490067', 'US2439-4', 'SAND', '7-8', 'Boys Two Pieces Set'],
            ['KVNURI02', '8684053490029', 'US2439-4', 'NAVY', '7-8', 'Boys Two Pieces Set'],
            ['KVNURI99', '9999999999999', 'US9999-4', 'RED', '4-5', 'Boys Two Pieces Set'],
        ]);
        $receiver = $this->writeReceiver([
            ['Amazon', '2001', '8684053490067'],
            ['Noon', '1024', '8684053490067'],
            ['Amazon', '2001', '8684053490029'],
        ]);
        NuriCatalogUpserter::import($nuri, 'Bangladesh', ExcelSheet::barcodesFromReceiver($receiver));

        $template = $this->writeNisTemplate();
        $output = sys_get_temp_dir().'/nis-out-'.uniqid().'.xlsx';
        $result = NoonNisExporter::export($template, $output, ExcelSheet::barcodesFromReceiver($receiver));

        $this->assertSame(1, $result['parents']);
        $this->assertSame(2, $result['children']);
        $this->assertSame([], $result['missing']);

        $rows = ExcelSheet::assocRows($output, 9);
        $this->assertCount(3, $rows);
        $this->assertSame('Parent', $rows[0]['parent_child_variation']);
        $this->assertSame('US2439-4', $rows[0]['seller_sku']);
        $this->assertSame('Child', $rows[1]['parent_child_variation']);
        $this->assertSame('Child', $rows[2]['parent_child_variation']);
        $skus = collect($rows)->pluck('seller_sku')->all();
        $this->assertContains('8684053490067', $skus);
        $this->assertContains('8684053490029', $skus);
        $this->assertNotContains('9999999999999', $skus);
        $this->assertSame('7-8 Years', collect($rows)->firstWhere('seller_sku', '8684053490067')['size_map']);
        $this->assertSame('SAND / 7-8', collect($rows)->firstWhere('seller_sku', '8684053490067')['size_variation']);
        $this->assertSame('NAVY / 7-8', collect($rows)->firstWhere('seller_sku', '8684053490029')['size_variation']);
        $this->assertSame('Beige', collect($rows)->firstWhere('seller_sku', '8684053490067')['colour_family']);
        $this->assertSame('Apparel', $rows[0]['family']);
        $this->assertSame('Sets', $rows[0]['product_type']);
        $this->assertSame('Co-Ord Sets', $rows[0]['product_subtype']);
        $this->assertSame('100', $rows[0]['msrp_ae']);
        $this->assertSame('100', collect($rows)->firstWhere('seller_sku', '8684053490067')['msrp_ae']);
        $this->assertSame('Std', $rows[0]['vat_rate_ae']);
        $this->assertSame('U.S. Polo Assn Kids Boys Two Pieces Set', $rows[0]['product_title_en']);
        $this->assertSame('U.S. Polo Assn Kids طقم قطعتين للأولاد', $rows[0]['product_title_ar']);
        $this->assertSame('طقم قطعتين للأولاد', $rows[0]['model_name_ar']);
        $this->assertSame('رملي', collect($rows)->firstWhere('seller_sku', '8684053490067')['colour_name_ar']);
        $this->assertSame('كحلي', collect($rows)->firstWhere('seller_sku', '8684053490029')['colour_name_ar']);
        $this->assertStringContainsString('طقم قطعتين للأولاد', collect($rows)->firstWhere('seller_sku', '8684053490067')['long_description_ar']);
        $this->assertSame('طقم من قطعتين', $rows[0]['whats_in_the_box_ar']);
        $this->assertSame(NoonNisMapper::IMAGE_URL_1, $rows[0]['image_url_1']);
        $this->assertSame(NoonNisMapper::IMAGE_URL_2, $rows[0]['image_url_2']);
        $this->assertSame(NoonNisMapper::IMAGE_URL_1, collect($rows)->firstWhere('seller_sku', '8684053490067')['image_url_1']);
        $this->assertSame(NoonNisMapper::IMAGE_URL_2, collect($rows)->firstWhere('seller_sku', '8684053490067')['image_url_2']);

        $pricePath = sys_get_temp_dir().'/nis-price-'.uniqid().'.xlsx';
        $priced = NoonNisExporter::writePriceSheet($pricePath, ExcelSheet::barcodesFromReceiver($receiver), '1455');
        $this->assertSame(2, $priced);
        $priceRows = ExcelSheet::assocRows($pricePath);
        $this->assertSame('ae', $priceRows[0]['country_code']);
        $this->assertSame('1455', $priceRows[0]['id_partner']);
        $this->assertSame('100', $priceRows[0]['price']);
        $this->assertContains('8684053490067', collect($priceRows)->pluck('partner_sku')->all());
    }

    public function test_official_price_update_keeps_psku_and_partner_sku(): void
    {
        $template = sys_get_temp_dir().'/noon-price-'.uniqid().'.csv';
        file_put_contents($template, implode(',', [
            'psku_code', 'country_code', 'id_partner', 'partner_sku', 'price', 'sale_price', 'sale_start', 'sale_end',
        ])."\n".implode(',', [
            'abc123', 'ae', '661132', '8684053490067', '', '', '', '',
        ])."\n");
        $output = sys_get_temp_dir().'/noon-price-out-'.uniqid().'.csv';

        $count = NoonNisExporter::fillOfficialPriceUpdate($template, $output);

        $this->assertSame(1, $count);
        $rows = ExcelSheet::assocRows($output);
        $this->assertSame('abc123', $rows[0]['psku_code']);
        $this->assertSame('ae', $rows[0]['country_code']);
        $this->assertSame('661132', $rows[0]['id_partner']);
        $this->assertSame('8684053490067', $rows[0]['partner_sku']);
        $this->assertSame('100', $rows[0]['price']);
        $slim = ExcelSheet::assocRows((string) preg_replace('/\.csv$/i', '-slim.csv', $output));
        $this->assertSame('abc123', $slim[0]['psku_code']);
        $this->assertSame('8684053490067', $slim[0]['partner_sku']);
        $this->assertSame('100', $slim[0]['price']);
    }

    public function test_nis_export_does_not_add_columns_missing_from_template(): void
    {
        $nuri = $this->writeNuri([
            ['KVNURI01', '8684053490067', 'US2439-4', 'SAND', '7-8', 'Boys Two Pieces Set'],
        ]);
        $receiver = $this->writeReceiver([
            ['Amazon', '2001', '8684053490067'],
        ]);
        NuriCatalogUpserter::import($nuri, 'Bangladesh', ExcelSheet::barcodesFromReceiver($receiver));

        $sheet = new Spreadsheet;
        $worksheet = $sheet->getActiveSheet();
        $worksheet->setTitle('template_data');
        $worksheet->fromArray([['family', 'brand', 'product_title', 'seller_sku']], null, 'A9');
        $template = $this->saveSheet($sheet);
        $output = sys_get_temp_dir().'/nis-cols-'.uniqid().'.xlsx';
        NoonNisExporter::export($template, $output, ExcelSheet::barcodesFromReceiver($receiver));

        $rows = ExcelSheet::assocRows($output, 9);
        $this->assertArrayNotHasKey('product_title_ar', $rows[0]);
        $this->assertSame('U.S. Polo Assn Kids Boys Two Pieces Set', $rows[0]['product_title']);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>  $rows
     */
    private function writeNuri(array $rows): string
    {
        $sheet = new Spreadsheet;
        $worksheet = $sheet->getActiveSheet();
        $worksheet->fromArray([[
            'FaturaTarih', 'Grup', 'Marka', 'Model No', 'Asorti', 'BedenSeti', 'Kumaş İngilizce',
            'İngilizce Tanım', 'Rusca Tanım', 'LotAdeti', 'Renk', 'İngilizce Renk', 'Adet',
            'Lot Barkod', 'Barkod', 'Varyant', 'BedenKodu', 'USD', 'Döviz',
        ]], null, 'A1');
        $row = 2;
        foreach ($rows as $item) {
            $worksheet->fromArray([[
                '7.04.2026', 'US POLO', 'U.S. Polo Assn Kids', $item[2], '1-1-1-1', '4-5/5-6/6-7/7-8',
                'Top And Bottom %100 Cotton', $item[5], 'Бермудская команда', '4', 'KUM', $item[3], '4',
                $item[0], $item[1], 'V5', $item[4], '13.65', 'USD',
            ]], null, 'A'.$row);
            $row++;
        }

        return $this->saveSheet($sheet);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     */
    private function writeReceiver(array $rows): string
    {
        $sheet = new Spreadsheet;
        $worksheet = $sheet->getActiveSheet();
        $worksheet->fromArray([[
            'Магазин', 'Короб', 'Модель', 'Название', 'Цвет', 'Размер', 'Лот-штрихкод', 'Штрихкод', 'Кол-во',
        ]], null, 'A1');
        $row = 2;
        foreach ($rows as $item) {
            $worksheet->fromArray([[
                $item[0], $item[1], 'US2439-4', 'Бермудская команда', 'SAND', '7-8', 'KVNURI01', $item[2], '1',
            ]], null, 'A'.$row);
            $row++;
        }

        return $this->saveSheet($sheet);
    }

    private function writeNisTemplate(): string
    {
        $sheet = new Spreadsheet;
        $worksheet = $sheet->getActiveSheet();
        $worksheet->setTitle('template_data');
        $codes = [
            'family', 'product_type', 'product_subtype', 'brand', 'product_title', 'product_title_en', 'product_title_ar',
            'seller_sku', 'parent_group_key', 'parent_child_variation', 'size_variation', 'size_map',
            'sizing_standard', 'colour_name', 'colour_name_ar', 'colour_family', 'model_name',
            'model_name_ar', 'model_number', 'item_condition', 'grade', 'department',
            'material_composition', 'gtin', 'country_of_origin', 'long_description', 'long_description_ar',
            'whats_in_the_box', 'whats_in_the_box_ar', 'feature_bullet_1', 'feature_bullet_1_ar',
            'feature_bullet_2', 'feature_bullet_2_ar', 'feature_bullet_3', 'feature_bullet_3_ar',
            'image_url_1', 'image_url_2', 'msrp_ae', 'vat_rate_ae',
        ];
        $worksheet->fromArray([$codes], null, 'A9');

        return $this->saveSheet($sheet);
    }

    private function saveSheet(Spreadsheet $sheet): string
    {
        $path = sys_get_temp_dir().'/nis-'.uniqid().'.xlsx';
        (new Xlsx($sheet))->save($path);

        return $path;
    }
}
