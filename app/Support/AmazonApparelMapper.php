<?php

namespace App\Support;

use App\Models\LotItem;

class AmazonApparelMapper
{
    /**
     * @return array<string, string|list<string>>
     */
    public static function parentRow(LotItem $item): array
    {
        $lot = $item->lot;
        $nameEn = (string) $lot?->name_en;
        $brand = (string) $lot?->brand;
        $model = (string) $lot?->model;
        $fabric = (string) $lot?->fabric_en;
        $origin = (string) $lot?->country_of_origin;
        $title = trim($brand.' '.$nameEn);

        return [
            ...self::shared($nameEn, $brand, $model, $fabric, $origin, $title),
            'SKU' => $model,
            'Parentage Level' => 'Parent',
            'Parent SKU' => '',
            'Item Name' => $title,
            'Product Id Type' => 'GTIN Exempt',
            'Product Id' => '',
            'Skip Offer' => 'Yes',
            'Item Condition' => '',
            'List Price Currency' => '',
            'List Price with Tax' => '',
            'Your Price AED (Sell on Amazon, AE)' => '',
            'Fulfillment Channel Code (AE)' => '',
            'Color' => '',
            'Color Map' => '',
            'Size' => '',
            'Bullet Point' => self::bullets($fabric, $origin, ''),
        ];
    }

    /**
     * @return array<string, string|list<string>>
     */
    public static function childRow(LotItem $item): array
    {
        $lot = $item->lot;
        $nameEn = (string) $lot?->name_en;
        $brand = (string) $lot?->brand;
        $model = (string) $lot?->model;
        $fabric = (string) $lot?->fabric_en;
        $origin = (string) $lot?->country_of_origin;
        $color = (string) $lot?->color_en;
        $size = NoonNisMapper::noonSize($item->size_code);
        $title = trim($brand.' '.$nameEn);
        $itemName = $title;
        if ($color !== '') {
            $itemName .= ', '.$color;
        }
        if ($size !== '') {
            $itemName .= ', '.$size;
        }

        return [
            ...self::shared($nameEn, $brand, $model, $fabric, $origin, $title),
            'SKU' => $item->barcode,
            'Parentage Level' => 'Child',
            'Parent SKU' => $model,
            'Item Name' => $itemName,
            'Product Id Type' => 'EAN',
            'Product Id' => $item->barcode,
            'Skip Offer' => 'No',
            'Item Condition' => 'New',
            'List Price Currency' => 'AED',
            'List Price with Tax' => NoonNisMapper::PRICE_AED,
            'Your Price AED (Sell on Amazon, AE)' => NoonNisMapper::PRICE_AED,
            'Fulfillment Channel Code (AE)' => 'Fulfilment by Merchant (Default)',
            'Color' => $color,
            'Color Map' => self::colorMap($color),
            'Size' => $size,
            'Bullet Point' => self::bullets($fabric, $origin, $color),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function shared(string $nameEn, string $brand, string $model, string $fabric, string $origin, string $title): array
    {
        $description = $title.'.';
        if ($fabric !== '') {
            $description .= ' Material: '.$fabric.'.';
        }
        if ($origin !== '') {
            $description .= ' Country of origin: '.$origin.'.';
        }

        return [
            'Product Type' => 'APPAREL',
            'Listing Action' => 'Create or Replace (Full Update)',
            'Variation Theme Name' => 'COLOR/SIZE',
            'Brand Name' => $brand,
            'Recommended Browse Nodes' => self::browseNode($nameEn),
            'Model Name' => $nameEn,
            'Manufacturer' => $brand,
            'Product Description' => $description,
            'Department Name' => self::department($nameEn),
            'Target Gender' => self::gender($nameEn),
            'Age Range Description' => self::ageRange($nameEn),
            'Material' => self::material($fabric),
            'Fabric Type' => $fabric,
            'Number of Items' => self::numberOfItems($nameEn),
            'Part Number' => $model,
            'Care Instructions' => 'Machine Wash',
            'Country of Origin' => $origin,
            'Are batteries required?' => 'No',
            'Are batteries included?' => 'No',
            'Dangerous Goods Regulations' => 'Not Applicable',
        ];
    }

    /**
     * @return list<string>
     */
    private static function bullets(string $fabric, string $origin, string $color): array
    {
        return array_values(array_filter([
            $fabric !== '' ? $fabric : '',
            $origin !== '' ? 'Country of origin: '.$origin : '',
            $color !== '' ? 'Colour: '.$color : 'Available in multiple colours and sizes',
        ]));
    }

    public static function department(string $nameEn): string
    {
        $name = mb_strtolower($nameEn);
        if (str_starts_with($name, 'baby boy')) {
            return 'Baby Boys';
        }
        if (str_starts_with($name, 'girl')) {
            return 'Girls';
        }
        if (str_starts_with($name, 'boy')) {
            return 'Boys';
        }
        if (str_starts_with($name, 'woman') || str_starts_with($name, 'women')) {
            return 'Womens';
        }
        if (str_starts_with($name, 'man') || str_starts_with($name, 'men')) {
            return 'Mens';
        }

        return 'Unisex';
    }

    public static function gender(string $nameEn): string
    {
        return match (self::department($nameEn)) {
            'Girls', 'Womens' => 'Female',
            default => 'Male',
        };
    }

    public static function ageRange(string $nameEn): string
    {
        return match (self::department($nameEn)) {
            'Baby Boys' => 'Infant',
            'Mens', 'Womens' => 'Adult',
            default => 'Kid',
        };
    }

    public static function numberOfItems(string $nameEn): string
    {
        $name = mb_strtolower($nameEn);
        if (str_contains($name, '3 pieces')) {
            return '3';
        }
        if (str_contains($name, 'shirt pyjamas')) {
            return '1';
        }
        if (
            str_contains($name, 'two pieces')
            || str_contains($name, 'bermudas')
            || str_contains($name, 'shirt & shorts')
            || str_contains($name, 'pyjamas')
        ) {
            return '2';
        }

        return '1';
    }

    public static function material(string $fabric): string
    {
        if (preg_match('/Elasthan|Pes|Mod|Viscose|\bEA\b|%\s*4|%\s*35|%\s*48|%\s*50|%\s*65|%\s*96/i', $fabric) === 1) {
            return 'Cotton Blend';
        }

        return 'Cotton';
    }

    public static function colorMap(string $color): string
    {
        $family = NoonNisMapper::colourFamily($color);

        return $family === 'Multicolour' ? 'Multicolor' : $family;
    }

    public static function browseNode(string $nameEn): string
    {
        $name = mb_strtolower($nameEn);
        $department = self::department($nameEn);

        if (str_contains($name, 'boxer') || str_contains($name, 'underpants') || str_contains($name, 'undershirt')) {
            return match ($department) {
                'Girls' => 'Fashion > Girls > Clothing > Underwear > Sets (11996473031)',
                'Mens' => 'Fashion > Men > Clothing > Underwear > Sets (11996660031)',
                'Baby Boys' => 'Fashion > Baby > Baby Boys > Clothing > Underwear > Sets (12066535031)',
                default => 'Fashion > Boys > Clothing > Underwear (11995932031)',
            };
        }

        if (str_contains($name, 'pyjama')) {
            return match ($department) {
                'Mens' => 'Fashion > Men > Clothing > Nightwear > Pajama Bottoms (11996625031)',
                'Womens' => 'Fashion > Women > Clothing > Nightwear, Lingerie & Underwear > Nightwear > Pajama Bottoms (12066929031)',
                'Baby Boys' => 'Fashion > Baby > Baby Boys > Clothing > Sleepwear & Robes > Pajama Bottoms (12066512031)',
                'Girls' => 'Fashion > Girls (11995842031)',
                default => 'Fashion > Boys > Clothing (11995853031)',
            };
        }

        return match ($department) {
            'Girls' => 'Fashion > Girls > Clothing > Activewear (11995969031)',
            'Mens' => 'Fashion (11497632031)',
            'Womens' => 'Fashion > Women > Clothing > Activewear > Sets (12066359031)',
            'Baby Boys' => 'Fashion > Baby > Baby Boys > Clothing (11995896031)',
            'Boys' => str_contains($name, 'two pieces') || str_contains($name, '3 pieces')
                ? 'Fashion > Boys > Clothing > Activewear > Sets (11996315031)'
                : 'Fashion > Boys > Clothing (11995853031)',
            default => 'Fashion > Boys > Clothing (11995853031)',
        };
    }
}
