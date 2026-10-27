<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a booking's history as the customer sees it (see the
 * booking_updates migration).
 */
#[Fillable(['booking_id', 'kind', 'status', 'message', 'amount', 'emailed_at', 'created_by'])]
class BookingUpdate extends Model
{
    public const KINDS = ['created', 'status', 'payment', 'message'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'emailed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
