<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Support\AmazonApparelExporter;
use App\Support\CatalogImporter;
use App\Support\ExcelSheet;
use App\Support\NuriCatalogUpserter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AmazonApparelTest extends TestCase
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

    public function test_apparel_export_writes_one_parent_and_color_size_children(): void
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

        $template = $this->writeApparelTemplate();
        $output = sys_get_temp_dir().'/amazon-out-'.uniqid().'.xlsx';
        $result = AmazonApparelExporter::export($template, $output, ExcelSheet::barcodesFromReceiver($receiver));

        $this->assertSame(1, $result['parents']);
        $this->assertSame(2, $result['children']);
        $this->assertSame([], $result['missing']);

        $rows = ExcelSheet::assocRows($output, 4);
        $this->assertCount(3, $rows);
        $this->assertSame('Parent', $rows[0]['Parentage Level']);
        $this->assertSame('US2439-4', $rows[0]['SKU']);
        $this->assertSame('COLOR/SIZE', $rows[0]['Variation Theme Name']);
        $this->assertSame('Yes', $rows[0]['Skip Offer']);
        $this->assertSame('APPAREL', $rows[0]['Product Type']);
        $this->assertSame('GTIN Exempt', $rows[0]['Product Id Type']);
        $this->assertSame('Child', $rows[1]['Parentage Level']);
        $this->assertSame('Child', $rows[2]['Parentage Level']);

        $skus = collect($rows)->pluck('SKU')->all();
        $this->assertContains('8684053490067', $skus);
        $this->assertContains('8684053490029', $skus);
        $this->assertNotContains('9999999999999', $skus);

        $sand = collect($rows)->firstWhere('SKU', '8684053490067');
        $navy = collect($rows)->firstWhere('SKU', '8684053490029');
        $this->assertSame('US2439-4', $sand['Parent SKU']);
        $this->assertSame('EAN', $sand['Product Id Type']);
        $this->assertSame('8684053490067', $sand['Product Id']);
        $this->assertSame('7-8 Years', $sand['Size']);
        $this->assertSame('SAND', $sand['Color']);
        $this->assertSame('Beige', $sand['Color Map']);
        $this->assertSame('NAVY', $navy['Color']);
        $this->assertSame('100', $sand['Your Price AED (Sell on Amazon, AE)']);
        $this->assertSame('100', $sand['List Price with Tax']);
        $this->assertSame('AED', $sand['List Price Currency']);
        $this->assertSame('No', $sand['Skip Offer']);
        $this->assertSame('COLOR/SIZE', $sand['Variation Theme Name']);
        $this->assertSame('Boys', $rows[0]['Department Name']);
        $this->assertSame('Kid', $rows[0]['Age Range Description']);
        $this->assertSame('Male', $rows[0]['Target Gender']);
        $this->assertStringContainsString('SAND', $sand['Item Name']);
        $this->assertStringContainsString('7-8 Years', $sand['Item Name']);
        $this->assertSame('Fashion > Boys > Clothing > Activewear > Sets (11996315031)', $rows[0]['Recommended Browse Nodes']);
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

    private function writeApparelTemplate(): string
    {
        $sheet = new Spreadsheet;
        $worksheet = $sheet->getActiveSheet();
        $worksheet->setTitle('Template');
        $labels = [
            'SKU', 'Product Type', 'Listing Action', 'Parentage Level', 'Parent SKU',
            'Variation Theme Name', 'Item Name', 'Brand Name', 'Product Id Type', 'Product Id',
            'Recommended Browse Nodes', 'Model Name', 'Manufacturer', 'Product Description',
            'Bullet Point', 'Department Name', 'Target Gender', 'Age Range Description',
            'Material', 'Fabric Type', 'Number of Items', 'Color Map', 'Color', 'Size',
            'Care Instructions', 'Skip Offer', 'Item Condition', 'List Price Currency',
            'List Price with Tax', 'Your Price AED (Sell on Amazon, AE)',
            'Fulfillment Channel Code (AE)', 'Country of Origin',
            'Are batteries required?', 'Are batteries included?', 'Dangerous Goods Regulations',
            'Part Number',
        ];
        $worksheet->fromArray([$labels], null, 'A4');

        return $this->saveSheet($sheet);
    }

    private function saveSheet(Spreadsheet $sheet): string
    {
        $path = sys_get_temp_dir().'/amazon-'.uniqid().'.xlsx';
        (new Xlsx($sheet))->save($path);

        return $path;
    }
}
