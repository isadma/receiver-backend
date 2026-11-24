<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxReceipt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_slug',
        'box_number',
        'lot_id',
        'lot_item_id',
        'qty',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lot, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * @return BelongsTo<LotItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(LotItem::class, 'lot_item_id');
    }

    public function shopName(): string
    {
        return match ($this->shop_slug) {
            'amazon' => 'Amazon',
            'noon' => 'Noon',
            'namshi' => 'Namshi',
            default => $this->shop_slug,
        };
    }
}
