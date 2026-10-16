<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'booking_id', 'method', 'is_online', 'status', 'amount', 'currency', 'transaction_id', 'gateway_reference',
    'reference', 'note', 'log', 'recorded_by', 'paid_at',
])]
class Payment extends Model
{
    use Trashable;

    public const OFFLINE_METHODS = ['bank' => 'Bank deposit / transfer', 'mobile_banking' => 'Mobile banking (bKash, Nagad…)', 'cash' => 'Cash', 'other' => 'Other'];

    public const GATEWAYS = ['sslcommerz' => 'SSLCommerz', 'dgepay' => 'DGePay'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_online' => 'boolean',
            'log' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Append a gateway request/response to the payment log.
     *
     * @param  array<string, mixed>  $data
     */
    public function addLog(string $event, array $data): void
    {
        $log = $this->log ?? [];
        $log[] = ['at' => now()->toIso8601String(), 'event' => $event, 'data' => $data];
        $this->log = array_slice($log, -30);
        $this->save();
    }

    public function methodLabel(): string
    {
        return self::GATEWAYS[$this->method] ?? self::OFFLINE_METHODS[$this->method] ?? ucfirst($this->method);
    }
}
