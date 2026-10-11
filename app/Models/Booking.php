<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'reference', 'package_id', 'package_price_id', 'snapshot', 'unit_price', 'travelers', 'quantity', 'total',
    'advance_type', 'advance_value', 'advance_amount', 'paid_amount', 'currency', 'status',
    'customer_name', 'customer_phone', 'customer_email', 'customer_address', 'customer_note',
    'admin_notes', 'locale', 'ip', 'read_at',
])]
class Booking extends Model
{
    use LogsActivity;

    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'advance_paid' => 'Advance paid',
        'fully_paid' => 'Fully paid',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
            'advance_value' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'travelers' => 'integer',
            'quantity' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(PackagePrice::class, 'package_price_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BookingDocument::class);
    }

    public function balance(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    /**
     * Advance still to pay online (zero once the advance is covered).
     */
    public function advanceDue(): float
    {
        return max(0, (float) $this->advance_amount - (float) $this->paid_amount);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    /**
     * A snapshot value in the given language, falling back to English.
     */
    public function snap(string $key, ?string $locale = null): ?string
    {
        $value = $this->snapshot[$key] ?? null;

        if (! is_array($value)) {
            return $value;
        }

        return $value[$locale ?? app()->getLocale()] ?? $value['en'] ?? (reset($value) ?: null);
    }

    /**
     * The customer's private link to the booking (no account needed).
     */
    public function publicUrl(): string
    {
        return URL::signedRoute('bookings.show', ['booking' => $this->reference]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'paid_amount', 'admin_notes'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
