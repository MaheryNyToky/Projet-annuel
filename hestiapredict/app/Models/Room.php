<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    protected $fillable = [
        'room_number',
        'type',
        'model',
        'base_price_ariary',
        'is_fixed_price',
    ];

    protected $casts = [
        'base_price_ariary' => 'integer',
        'is_fixed_price' => 'boolean',
    ];

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'booking_room')
            ->withPivot('price_snapshot_ariary')
            ->withTimestamps();
    }

    public function getIdentifierAttribute(): string
    {
        return "{$this->type} - {$this->model}";
    }
}
