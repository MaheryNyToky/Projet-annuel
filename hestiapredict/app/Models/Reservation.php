<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reservation extends Model
{
    public const ACTIVE_STATUSES = ['en_attente', 'arrive'];

    protected $fillable = [
        'client_name',
        'client_phone',
        'customer_phone',
        'customer_email',
        'booking_reference',
        'source',
        'is_booking_com',
        'check_in_date',
        'check_out_date',
        'status',
        'payment_status',
        'cancelled_by_name',
        'cancelled_at',
        'user_id',
        'extra_beds',
        'extra_mattresses',
    ];

    protected $casts = [
        'is_booking_com' => 'boolean',
        'check_in_date' => 'date:Y-m-d',
        'check_out_date' => 'date:Y-m-d',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'booking_room')
            ->withPivot('price_snapshot_ariary')
            ->withTimestamps();
    }

    public function guest()
    {
        return $this->hasOne(Guest::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function scopeActiveDuring($query, string $date)
    {
        return $query
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->where('check_in_date', '<=', $date)
            ->where('check_out_date', '>', $date);
    }
}
