<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['source', 'page_id', 'page_section_id', 'booking_id', 'name', 'phone', 'email', 'data', 'status', 'notes', 'locale', 'ip', 'read_at'])]
class Inquiry extends Model
{
    use Trashable;

    public const STATUSES = ['new', 'in_progress', 'closed'];

    /** Where an enquiry came from, as staff call it. */
    public const SOURCES = [
        'contact_form' => 'Contact form',
        'fare_quote' => 'Air ticket quote',
        'visa_enquiry' => 'Visa enquiry',
        'campaign_form' => 'Campaign form',
        'lead_form' => 'Enquiry form',
    ];

    /**
     * The source of a new enquiry: the contact form, or for lead forms what
     * they ask (a fare quote, a visa) or where they sit (a campaign page).
     *
     * @param  array<string, mixed>  $data
     */
    public static function sourceFor(PageSection $section, array $data): string
    {
        return match (true) {
            $section->component === 'contact_form' => 'contact_form',
            array_key_exists('trip_type', $data) || array_key_exists('from_city', $data) => 'fare_quote',
            array_key_exists('visa_country', $data) || array_key_exists('visa_type', $data) => 'visa_enquiry',
            (bool) $section->page?->isCampaign() => 'campaign_form',
            default => 'lead_form',
        };
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst(str_replace('_', ' ', (string) $this->source));
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * The submitted details as label-free text, for the admin, exports and emails:
     * nested values (the extra flights of a multi-city quote) become readable lines.
     *
     * @return array<string, string>
     */
    public function readableData(): array
    {
        return collect($this->data ?? [])->except('page')->map(function ($value, $key) {
            if ($key === 'more_flights' && is_array($value)) {
                return collect($value)->values()->map(fn ($leg, $i) => sprintf(
                    'Flight %d: %s → %s%s',
                    $i + 2,
                    $leg['from'] ?? '?',
                    $leg['to'] ?? '?',
                    filled($leg['date'] ?? null) ? ' · '.$leg['date'] : '',
                ))->implode("\n");
            }

            return is_array($value) ? implode(', ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $value)) : (string) $value;
        })->all();
    }

    /** The booking staff made from this inquiry, if any. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Staff notes, newest first. */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest('id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
