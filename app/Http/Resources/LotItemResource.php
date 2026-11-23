<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'size_code' => $this->size_code,
            'pieces_per_lot' => $this->pieces_per_lot,
            'amazon_received_qty' => $this->amazon_received_qty,
            'noon_received_qty' => $this->noon_received_qty,
            'namshi_received_qty' => $this->namshi_received_qty,
            'total_received_qty' => $this->totalReceived(),
        ];
    }
}
