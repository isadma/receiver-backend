<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LotItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lot_id',
        'barcode',
        'size_code',
        'pieces_per_lot',
        'invoice_qty',
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
            'pieces_per_lot' => 'integer',
            'invoice_qty' => 'integer',
            'amazon_received_qty' => 'integer',
            'noon_received_qty' => 'integer',
            'namshi_received_qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function totalReceived(): int
    {
        return $this->amazon_received_qty + $this->noon_received_qty + $this->namshi_received_qty;
    }
}
