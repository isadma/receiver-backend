<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'name' => $this->name,
            'total_expected_qty' => $this->total_expected_qty,
            'amazon_expected_qty' => $this->amazon_expected_qty,
            'noon_expected_qty' => $this->noon_expected_qty,
            'namshi_expected_qty' => $this->namshi_expected_qty,
            'total_received_qty' => $this->total_received_qty,
            'amazon_received_qty' => $this->amazon_received_qty,
            'noon_received_qty' => $this->noon_received_qty,
            'namshi_received_qty' => $this->namshi_received_qty,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
