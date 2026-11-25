<?php

namespace Tests\Feature;

use App\Support\ExcelSheet;
use App\Support\NoonAsnExporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class NoonAsnTest extends TestCase
{
    public function test_export_writes_one_asn_per_noon_box_with_kids_and_adult_packs(): void
    {
        $receiver = $this->writeReceiver([
            ['Amazon', '2001', '8684053490067', '7-8', '4'],
            ['Noon', '1001', '8684053490067', '7-8', '4'],
            ['Noon', '1001', '8684053490067', '7-8', '2'],
            ['Noon', '1002', '8684053350996', 'L', '3'],
        ]);
        $template = $this->writeAsnTemplate();
        $output = sys_get_temp_dir().'/noon-asns-'.uniqid();

        $result = NoonAsnExporter::export($receiver, $template, $output);

        $this->assertSame(2, $result['boxes']);
        $this->assertFileExists($output.'/ASN-1001.xlsx');
        $this->assertFileExists($output.'/ASN-1002.xlsx');
        $this->assertFileDoesNotExist($output.'/ASN-2001.xlsx');

        $kids = ExcelSheet::assocRows($output.'/ASN-1001.xlsx', 10);
        $this->assertCount(1, $kids);
        $this->assertSame('8684053490067', $kids[0]['Partner SKU / SKU']);
        $this->assertSame('6', $kids[0]['Quantity']);
        $this->assertSame('30', $kids[0]['Longest Side (cm)']);
        $this->assertSame('25', $kids[0]['Median Side (cm)']);
        $this->assertSame('2', $kids[0]['Shortest Side (cm)']);
        $this->assertSame('0.25', $kids[0]['Weight (kg)']);

        $adult = ExcelSheet::assocRows($output.'/ASN-1002.xlsx', 10);
        $this->assertCount(1, $adult);
        $this->assertSame('8684053350996', $adult[0]['Partner SKU / SKU']);
        $this->assertSame('3', $adult[0]['Quantity']);
        $this->assertSame('35', $adult[0]['Longest Side (cm)']);
        $this->assertSame('25', $adult[0]['Median Side (cm)']);
        $this->assertSame('1', $adult[0]['Shortest Side (cm)']);
        $this->assertSame('0.4', $adult[0]['Weight (kg)']);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string}>  $rows
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
                $item[0], $item[1], 'US2439-4', 'Set', 'SAND', $item[3], 'KVNURI01', $item[2], $item[4],
            ]], null, 'A'.$row);
            $row++;
        }

        return $this->saveSheet($sheet);
    }

    private function writeAsnTemplate(): string
    {
        $sheet = new Spreadsheet;
        $worksheet = $sheet->getActiveSheet();
        $worksheet->setTitle('Template');
        $worksheet->fromArray([[
            'Partner SKU / SKU', 'Quantity', 'Longest Side (cm)', 'Median Side (cm)', 'Shortest Side (cm)', 'Weight (kg)',
        ]], null, 'A10');

        return $this->saveSheet($sheet);
    }

    private function saveSheet(Spreadsheet $sheet): string
    {
        $path = sys_get_temp_dir().'/asn-'.uniqid().'.xlsx';
        (new Xlsx($sheet))->save($path);

        return $path;
    }
}
