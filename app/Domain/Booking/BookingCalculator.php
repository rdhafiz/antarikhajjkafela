<?php

namespace App\Domain\Booking;

use App\Models\Package;
use App\Models\PackagePrice;

/**
 * Booking amounts, always calculated on the server.
 *
 *   per person: total = price × travelers
 *   per room:   total = price × rooms
 *   per group:  total = price (once)
 *
 * A fixed advance is charged per traveler; a percentage advance is a share
 * of the total. The advance never exceeds the total.
 */
class BookingCalculator
{
    /**
     * @return array{unit_price: float, quantity: int, total: float, advance_type: string, advance_value: float|null, advance_amount: float}
     */
    public function calculate(Package $package, PackagePrice $price, int $travelers, ?int $rooms = null): array
    {
        $unitPrice = round((float) $price->price, 2);

        $quantity = match ($price->unit) {
            'room' => max(1, (int) ($rooms ?? 1)),
            'group' => 1,
            default => $travelers,
        };

        $total = round($unitPrice * $quantity, 2);
        $type = $package->advance_type ?: 'none';
        $value = $package->advance_value !== null ? (float) $package->advance_value : null;

        $advance = match ($type) {
            'fixed' => round(($value ?? 0) * $travelers, 2),
            'percentage' => round($total * min(100, max(0, $value ?? 0)) / 100, 2),
            default => 0.0,
        };

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total' => $total,
            'advance_type' => $type,
            'advance_value' => $type === 'none' ? null : $value,
            'advance_amount' => min($advance, $total),
        ];
    }
}
