<?php

namespace App\Http\Resources;

use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(BookingService::class)->formatReservation($this->resource);
    }
}
