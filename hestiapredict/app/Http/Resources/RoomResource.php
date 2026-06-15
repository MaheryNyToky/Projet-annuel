<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room_number' => $this->room_number,
            'type' => $this->type,
            'model' => $this->model,
            'base_price_ariary' => $this->base_price_ariary,
            'fixed_price_ariary' => $this->base_price_ariary,
            'is_fixed_price' => $this->is_fixed_price,
        ];
    }
}
