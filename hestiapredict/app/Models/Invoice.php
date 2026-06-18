<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'invoice_number',
        'total_amount_ariary',
        'tax_amount_ariary',
        'discount_mode',
        'discount_value',
        'discount_amount_ariary',
        'deposit_amount_ariary',
        'pdf_path',
        'finalized_at',
        'status',
        'document_type',
    ];

    protected $casts = [
        'total_amount_ariary' => 'integer',
        'tax_amount_ariary' => 'integer',
        'discount_value' => 'decimal:2',
        'discount_amount_ariary' => 'integer',
        'deposit_amount_ariary' => 'integer',
        'finalized_at' => 'datetime',
    ];

    protected $appends = [
        'paid_amount_ariary',
        'balance_amount_ariary',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaidAmountAriaryAttribute(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->sum('amount_ariary');
        }

        return (int) $this->payments()->sum('amount_ariary');
    }

    public function getBalanceAmountAriaryAttribute(): int
    {
        return max(0, (int) $this->total_amount_ariary - $this->paid_amount_ariary);
    }
}
