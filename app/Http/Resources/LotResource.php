<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items', fn () => LotItemResource::collection($this->items), []);

        return [
            'id' => $this->id,
            'lot_barcode' => $this->lot_barcode,
            'invoice_date' => $this->invoice_date,
            'group_name' => $this->group_name,
            'brand' => $this->brand,
            'model' => $this->model,
            'assortment' => $this->assortment,
            'size_set' => $this->size_set,
            'fabric_en' => $this->fabric_en,
            'name_en' => $this->name_en,
            'name_ru' => $this->name_ru,
            'color' => $this->color,
            'color_en' => $this->color_en,
            'variant' => $this->variant,
            'items' => $items,
            'pieces_per_lot' => $this->whenLoaded(
                'items',
                fn () => $this->items->sum('pieces_per_lot'),
            ),
            'amazon_received_qty' => $this->whenLoaded(
                'items',
                fn () => $this->items->sum('amazon_received_qty'),
            ),
            'noon_received_qty' => $this->whenLoaded(
                'items',
                fn () => $this->items->sum('noon_received_qty'),
            ),
            'namshi_received_qty' => $this->whenLoaded(
                'items',
                fn () => $this->items->sum('namshi_received_qty'),
            ),
        ];
    }
}
