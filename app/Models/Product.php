<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'barcode',
        'sku',
        'name',
        'total_expected_qty',
        'amazon_expected_qty',
        'noon_expected_qty',
        'namshi_expected_qty',
        'total_received_qty',
        'amazon_received_qty',
        'noon_received_qty',
        'namshi_received_qty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_expected_qty' => 'integer',
            'amazon_expected_qty' => 'integer',
            'noon_expected_qty' => 'integer',
            'namshi_expected_qty' => 'integer',
            'total_received_qty' => 'integer',
            'amazon_received_qty' => 'integer',
            'noon_received_qty' => 'integer',
            'namshi_received_qty' => 'integer',
        ];
    }
}
