<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A passport, NID or other file uploaded with a booking. Stored on the
 * private disk and only served to admins through a permission-checked route.
 */
#[Fillable(['booking_id', 'kind', 'path', 'original_name', 'mime_type', 'size'])]
class BookingDocument extends Model
{
    public const DISK = 'local';

    public const KINDS = ['passport' => 'Passport', 'nid' => 'National ID', 'other' => 'Other document'];

    protected static function booted(): void
    {
        static::deleted(fn (BookingDocument $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
