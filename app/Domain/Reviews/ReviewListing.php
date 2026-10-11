<?php

namespace App\Domain\Reviews;

use App\Models\Page;
use App\Models\Review;

/**
 * All published reviews on the Reviews page.
 */
class ReviewListing
{
    public function view(): string
    {
        return 'site.reviews.listing';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Page $page): array
    {
        $reviews = Review::published()->with('photo')->ordered()->get();
        $rated = $reviews->whereNotNull('rating');

        return [
            'reviews' => $reviews,
            'videoReviews' => $reviews->filter(fn (Review $r) => $r->youtubeId()),
            'textReviews' => $reviews->filter(fn (Review $r) => filled($r->getTranslation('text', app()->getLocale()))),
            'average' => $rated->isNotEmpty() ? round($rated->avg('rating'), 1) : null,
            'ratedCount' => $rated->count(),
        ];
    }
}
