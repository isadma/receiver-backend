<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lot_barcode',
        'invoice_date',
        'group_name',
        'brand',
        'model',
        'assortment',
        'size_set',
        'fabric_en',
        'name_en',
        'name_ru',
        'color',
        'color_en',
        'variant',
        'usd',
        'currency',
        'country_of_origin',
        'invoice_lot_qty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_lot_qty' => 'integer',
        ];
    }

    /**
     * @return HasMany<LotItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LotItem::class)->orderBy('id');
    }
}
