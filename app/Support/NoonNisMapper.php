<?php

namespace App\Support;

use App\Models\LotItem;

class NoonNisMapper
{
    public const PRICE_AED = '100';

    public const IMAGE_URL_1 = 'https://receiver.meredow.com/images/image-1.jpg';

    public const IMAGE_URL_2 = 'https://receiver.meredow.com/images/image-2.jpg';

    /**
     * @var array<string, string>
     */
    private const COLOUR_FAMILY = [
        'ANTHRACITE' => 'Grey',
        'BEIGE' => 'Beige',
        'BLACK' => 'Black',
        'BLUE' => 'Blue',
        'BROWN' => 'Brown',
        'CHOCOLATE' => 'Brown',
        'CLARET RED' => 'Red',
        'CORAL' => 'Pink',
        'DARK BLUE' => 'Blue',
        'DARK GREEN' => 'Green',
        'DARK GREY' => 'Grey',
        'DARK INDIGO' => 'Blue',
        'DARK VISON' => 'Grey',
        'GREEN' => 'Green',
        'GREY' => 'Grey',
        'GREY MELANGE' => 'Grey',
        'INDIGO' => 'Blue',
        'LIGHT BLUE' => 'Blue',
        'LIGHT PINK' => 'Pink',
        'MINT' => 'Green',
        'NAVY' => 'Blue',
        'NILE' => 'Green',
        'NILE GREEN' => 'Green',
        'PETROL GREEN' => 'Green',
        'PINK' => 'Pink',
        'POWDER PINK' => 'Pink',
        'RED' => 'Red',
        'SALMON' => 'Pink',
        'SAND' => 'Beige',
        'SEA GREEN' => 'Green',
        'SMOKY' => 'Grey',
        'SPACE' => 'Grey',
        'STONE' => 'Beige',
        'VISON' => 'Grey',
        'WHITE' => 'White',
        'WHITE/NILE' => 'Multicolour',
        'WHITE/PINK' => 'Multicolour',
        'WHITE/SALMON' => 'Multicolour',
        'WHITE/SNOW MELANGE/SALMON/LIGHT PINK/LILAC' => 'Multicolour',
        'YELLOW' => 'Yellow',
    ];

    /**
     * @var array<string, string>
     */
    private const NAME_AR = [
        'Baby Boy 3 Pieces Set' => 'طقم 3 قطع للأطفال الرضع',
        'Baby Boy Dungarees' => 'سالوبيت للأطفال الرضع',
        'Baby Boy Shorts Set' => 'طقم شورت للأطفال الرضع',
        'Boys Boxer' => 'بوكسر للأولاد',
        'Boys Pyjamas Set' => 'طقم بيجاما للأولاد',
        'Boys Two Pieces Set' => 'طقم قطعتين للأولاد',
        'Girls Pyjamas Set' => 'طقم بيجاما للبنات',
        'Girls Shirt & Shorts Set' => 'طقم قميص وشورت للبنات',
        'Girls Shirt Pyjamas' => 'قميص بيجاما للبنات',
        'Girls Two Pieces Set' => 'طقم قطعتين للبنات',
        'Girls Underpants' => 'سروال داخلي للبنات',
        'Girls Undershirt' => 'فانيلة داخلية للبنات',
        'Man 3 Pieces Set' => 'طقم 3 قطع للرجال',
        'Man Bermudas Set' => 'طقم برمودا للرجال',
        'Man Pyjamas Set' => 'طقم بيجاما للرجال',
        'Woman Pyjamas Set' => 'طقم بيجاما للنساء',
        'Woman Shirt Pyjamas' => 'قميص بيجاما للنساء',
    ];

    /**
     * @var array<string, string>
     */
    private const COLOUR_AR = [
        'ANTHRACITE' => 'أنثراسايت',
        'BEIGE' => 'بيج',
        'BLACK' => 'أسود',
        'BLUE' => 'أزرق',
        'BROWN' => 'بني',
        'CHOCOLATE' => 'شوكولاتة',
        'CLARET RED' => 'خمري',
        'CORAL' => 'مرجاني',
        'DARK BLUE' => 'أزرق داكن',
        'DARK GREEN' => 'أخضر داكن',
        'DARK GREY' => 'رمادي داكن',
        'DARK INDIGO' => 'نيلي داكن',
        'DARK VISON' => 'فيزون داكن',
        'GREEN' => 'أخضر',
        'GREY' => 'رمادي',
        'GREY MELANGE' => 'رمادي ميلانج',
        'INDIGO' => 'نيلي',
        'LIGHT BLUE' => 'أزرق فاتح',
        'LIGHT PINK' => 'وردي فاتح',
        'MINT' => 'نعناعي',
        'NAVY' => 'كحلي',
        'NILE' => 'نيلي',
        'NILE GREEN' => 'أخضر نيلي',
        'PETROL GREEN' => 'أخضر بترولي',
        'PINK' => 'وردي',
        'POWDER PINK' => 'وردي بودرة',
        'RED' => 'أحمر',
        'SALMON' => 'سلمون',
        'SAND' => 'رملي',
        'SEA GREEN' => 'أخضر بحري',
        'SMOKY' => 'دخاني',
        'SPACE' => 'سبيس',
        'STONE' => 'حجري',
        'VISON' => 'فيزون',
        'WHITE' => 'أبيض',
        'WHITE/NILE' => 'أبيض/نيلي',
        'WHITE/PINK' => 'أبيض/وردي',
        'WHITE/SALMON' => 'أبيض/سلمون',
        'WHITE/SNOW MELANGE/SALMON/LIGHT PINK/LILAC' => 'أبيض/ميلانج ثلجي/سلمون/وردي فاتح/ليلكي',
        'YELLOW' => 'أصفر',
    ];

    /**
     * @var array<string, string>
     */
    private const FABRIC_AR = [
        '%100 Cotton' => '100% قطن',
        '%100 Cotton Bott:%96 Cott %4 Elasthan' => 'الأعلى 100% قطن، الأسفل 96% قطن 4% إيلاستان',
        '%48 Cotton %48 Pes %4 EA  Bott:%96 Cott %4 Elasthan' => 'الأعلى 48% قطن 48% بوليستر 4% إيلاستان، الأسفل 96% قطن 4% إيلاستان',
        '%96 Cott %4 Elasthan' => '96% قطن 4% إيلاستان',
        'Top And Bottom %100 Cotton' => 'الأعلى والأسفل 100% قطن',
        'Top And Bottom %50 Cotton %50 Mod' => 'الأعلى والأسفل 50% قطن 50% مودال',
        'Top And Bottom %65 Pes %35 Viscose' => 'الأعلى والأسفل 65% بوليستر 35% فيسكوز',
        'Top And Bottom %96 Cott %4 Elasthan' => 'الأعلى والأسفل 96% قطن 4% إيلاستان',
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const BOX_CONTENTS = [
        'Baby Boy 3 Pieces Set' => ['3-piece set', 'طقم من 3 قطع'],
        'Baby Boy Dungarees' => ['Dungarees', 'سالوبيت'],
        'Baby Boy Shorts Set' => ['Shorts set', 'طقم شورت'],
        'Boys Boxer' => ['Boxer', 'بوكسر'],
        'Boys Pyjamas Set' => ['Pyjama set', 'طقم بيجاما'],
        'Boys Two Pieces Set' => ['Two-piece set', 'طقم من قطعتين'],
        'Girls Pyjamas Set' => ['Pyjama set', 'طقم بيجاما'],
        'Girls Shirt & Shorts Set' => ['Shirt and shorts set', 'طقم قميص وشورت'],
        'Girls Shirt Pyjamas' => ['Pyjama shirt', 'قميص بيجاما'],
        'Girls Two Pieces Set' => ['Two-piece set', 'طقم من قطعتين'],
        'Girls Underpants' => ['Underpants', 'سروال داخلي'],
        'Girls Undershirt' => ['Undershirt', 'فانيلة داخلية'],
        'Man 3 Pieces Set' => ['3-piece set', 'طقم من 3 قطع'],
        'Man Bermudas Set' => ['Bermuda set', 'طقم برمودا'],
        'Man Pyjamas Set' => ['Pyjama set', 'طقم بيجاما'],
        'Woman Pyjamas Set' => ['Pyjama set', 'طقم بيجاما'],
        'Woman Shirt Pyjamas' => ['Pyjama shirt', 'قميص بيجاما'],
    ];

    /**
     * @var array<string, string>
     */
    private const ORIGIN_AR = [
        'Bangladesh' => 'بنغلاديش',
        'Egypt' => 'مصر',
    ];

    /**
     * @return array<string, string>
     */
    public static function parentRow(LotItem $item): array
    {
        $lot = $item->lot;
        $shared = self::shared((string) $lot?->name_en, (string) $lot?->brand, (string) $lot?->model, (string) $lot?->fabric_en, (string) $lot?->country_of_origin);

        return [
            ...$shared,
            'seller_sku' => (string) $lot?->model,
            'parent_group_key' => (string) $lot?->model,
            'parent_child_variation' => 'Parent',
            'size_variation' => '',
            'size_map' => '',
            'sizing_standard' => '',
            'colour_name' => '',
            'colour_name_en' => '',
            'colour_name_ar' => '',
            'colour_family' => '',
            'gtin' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function childRow(LotItem $item, bool $includeColorInSize = false): array
    {
        $lot = $item->lot;
        $size = self::sizeCode($item->size_code);
        $color = (string) $lot?->color_en;
        $shared = self::shared((string) $lot?->name_en, (string) $lot?->brand, (string) $lot?->model, (string) $lot?->fabric_en, (string) $lot?->country_of_origin, $color);

        return [
            ...$shared,
            'seller_sku' => $item->barcode,
            'parent_group_key' => (string) $lot?->model,
            'parent_child_variation' => 'Child',
            'size_variation' => $includeColorInSize && $color !== '' ? $color.' / '.$size : $size,
            'size_map' => self::noonSize($size),
            'sizing_standard' => self::sizingStandard($size),
            'colour_name' => $color,
            'colour_name_en' => $color,
            'colour_name_ar' => self::colourAr($color),
            'colour_family' => self::colourFamily($color),
            'gtin' => $item->barcode,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function shared(string $nameEn, string $brand, string $model, string $fabric, string $origin, string $color = ''): array
    {
        [$family, $type, $subtype] = self::category($nameEn);
        $nameAr = self::nameAr($nameEn);
        $titleEn = trim($brand.' '.$nameEn);
        $titleAr = trim($brand.' '.$nameAr);
        $fabricAr = self::fabricAr($fabric);
        $originAr = self::originAr($origin);
        $contents = self::BOX_CONTENTS[$nameEn] ?? [$nameEn, $nameAr];
        $colorAr = self::colourAr($color);

        $descriptionEn = $titleEn.'.';
        $descriptionAr = $titleAr.'.';
        if ($fabric !== '') {
            $descriptionEn .= ' Material: '.$fabric.'.';
            $descriptionAr .= ' الخامة: '.$fabricAr.'.';
        }
        if ($color !== '') {
            $descriptionEn .= ' Colour: '.$color.'.';
            $descriptionAr .= ' اللون: '.$colorAr.'.';
        }
        if ($origin !== '') {
            $descriptionEn .= ' Country of origin: '.$origin.'.';
            $descriptionAr .= ' بلد المنشأ: '.$originAr.'.';
        }

        $bulletsEn = array_values(array_filter([
            $fabric !== '' ? $fabric : '',
            $color !== '' ? 'Colour: '.$color : '',
            $origin !== '' ? 'Country of origin: '.$origin : '',
        ]));
        $bulletsAr = array_values(array_filter([
            $fabric !== '' ? $fabricAr : '',
            $color !== '' ? 'اللون: '.$colorAr : '',
            $origin !== '' ? 'بلد المنشأ: '.$originAr : '',
        ]));

        return [
            'family' => $family,
            'product_type' => $type,
            'product_subtype' => $subtype,
            'brand' => $brand,
            'product_title' => $titleEn,
            'product_title_en' => $titleEn,
            'product_title_ar' => $titleAr,
            'model_name' => $nameEn,
            'model_name_ar' => $nameAr,
            'model_number' => $model,
            'item_condition' => 'New',
            'grade' => 'New',
            'department' => self::department($nameEn),
            'material_composition' => $fabric,
            'material_composition_en' => $fabric,
            'material_composition_ar' => $fabricAr,
            'country_of_origin' => $origin,
            'long_description' => $descriptionEn,
            'long_description_en' => $descriptionEn,
            'long_description_ar' => $descriptionAr,
            'whats_in_the_box' => $contents[0],
            'whats_in_the_box_en' => $contents[0],
            'whats_in_the_box_ar' => $contents[1],
            'feature_bullet_1' => $bulletsEn[0] ?? '',
            'feature_bullet_1_en' => $bulletsEn[0] ?? '',
            'feature_bullet_1_ar' => $bulletsAr[0] ?? '',
            'feature_bullet_2' => $bulletsEn[1] ?? '',
            'feature_bullet_2_en' => $bulletsEn[1] ?? '',
            'feature_bullet_2_ar' => $bulletsAr[1] ?? '',
            'feature_bullet_3' => $bulletsEn[2] ?? '',
            'feature_bullet_3_en' => $bulletsEn[2] ?? '',
            'feature_bullet_3_ar' => $bulletsAr[2] ?? '',
            'image_url_1' => self::IMAGE_URL_1,
            'image_url_2' => self::IMAGE_URL_2,
            'msrp_ae' => self::PRICE_AED,
            'vat_rate_ae' => 'Std',
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    public static function category(string $nameEn): array
    {
        $name = mb_strtolower($nameEn);
        if (str_contains($name, 'shirt pyjamas')) {
            return ['Apparel', 'Loungewear/Sleepwear', 'Pyjama Top'];
        }
        if (str_contains($name, 'pyjamas')) {
            return ['Apparel', 'Loungewear/Sleepwear', 'Pyjama Set'];
        }

        return ['Apparel', 'Sets', 'Co-Ord Sets'];
    }

    public static function department(string $nameEn): string
    {
        $name = mb_strtolower($nameEn);
        if (str_starts_with($name, 'girl')) {
            return 'Girls';
        }
        if (str_starts_with($name, 'boy') || str_starts_with($name, 'baby boy')) {
            return 'Boys';
        }
        if (str_starts_with($name, 'woman') || str_starts_with($name, 'women')) {
            return 'Women';
        }
        if (str_starts_with($name, 'man') || str_starts_with($name, 'men')) {
            return 'Men';
        }

        return 'Unisex';
    }

    public static function colourFamily(string $color): string
    {
        $key = mb_strtoupper(trim($color));

        return self::COLOUR_FAMILY[$key] ?? 'Multicolour';
    }

    public static function nameAr(string $nameEn): string
    {
        return self::NAME_AR[$nameEn] ?? $nameEn;
    }

    public static function colourAr(string $color): string
    {
        if ($color === '') {
            return '';
        }

        $key = mb_strtoupper(trim($color));

        return self::COLOUR_AR[$key] ?? $color;
    }

    public static function fabricAr(string $fabric): string
    {
        return self::FABRIC_AR[$fabric] ?? $fabric;
    }

    public static function originAr(string $origin): string
    {
        return self::ORIGIN_AR[$origin] ?? $origin;
    }

    public static function sizeCode(string $size): string
    {
        return trim((string) preg_replace('/\s*Ya[sşŞ]\s*$/iu', '', $size));
    }

    public static function noonSize(string $size): string
    {
        $size = self::sizeCode($size);
        if (preg_match('/^\d+-\d+$/', $size) === 1) {
            return $size.' Years';
        }

        return $size;
    }

    public static function sizingStandard(string $size): string
    {
        return preg_match('/^\d+-\d+$/', self::sizeCode($size)) === 1 ? 'Age(Kids)' : 'International';
    }
}
