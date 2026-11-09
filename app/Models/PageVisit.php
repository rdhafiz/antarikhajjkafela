<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per public page view, kept on the visit-log connection. */
class PageVisit extends Model
{
    public const UPDATED_AT = null;

    public const SUBJECTS = ['home', 'page', 'campaign', 'service', 'package', 'booking', 'post', 'album', 'packages', 'campaigns'];

    public const CHANNEL_LABELS = [
        'social' => 'Social',
        'paid_social' => 'Paid social',
        'organic_search' => 'Search',
        'paid_search' => 'Paid search',
        'referral' => 'Referral',
        'email' => 'Email',
        'sms' => 'SMS',
        'direct' => 'Direct',
        'other' => 'Other',
    ];

    protected $guarded = [];

    protected $casts = ['is_bot' => 'boolean'];

    public function getConnectionName(): ?string
    {
        return config('visits.connection');
    }
}
