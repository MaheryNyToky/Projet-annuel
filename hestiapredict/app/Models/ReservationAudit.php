<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationAudit extends Model
{
    protected $fillable = [
        'reservation_id',
        'action',
        'actor_name',
        'actor_role',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
