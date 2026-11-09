<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** What is known about one visitor IP, kept on the visit-log connection. */
class IpLocation extends Model
{
    public const MAX_ATTEMPTS = 3;

    protected $primaryKey = 'ip';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'is_proxy' => 'boolean',
        'is_hosting' => 'boolean',
        'resolved_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('visits.connection');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')->where('attempts', '<', self::MAX_ATTEMPTS)->orderBy('created_at');
    }

    /** True for addresses worth looking up: a valid IP outside private and reserved ranges. */
    public static function isPublic(mixed $ip): bool
    {
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    public function label(): string
    {
        return implode(', ', array_filter([$this->city, $this->country]));
    }
}
