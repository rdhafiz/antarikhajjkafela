<?php

namespace App\Domain\Travel;

use App\Models\Service;

/**
 * The fixed parts of the Hajj and Umrah service pages: what the packages
 * include (from the agency's own facility lists) and the journey cards for
 * Makkah, Madinah and the preparation before departure.
 */
class PilgrimageGuide
{
    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function included(Service $service): array
    {
        $hajj = $service->slug === 'hajj';

        return [
            ['icon' => 'book', 'title' => __('Scholars and guides beside you'), 'text' => __('Workshops before you fly, then an experienced scholar and guides with you from departure until you are home.')],
            ['icon' => 'plane', 'title' => __('Flights'), 'text' => $hajj
                ? __('Saudia, Biman Bangladesh or another government-approved airline.')
                : __('Direct or transit flights from Dhaka, booked with your package.')],
            ['icon' => 'hotel', 'title' => __('Hotels near the Haram'), 'text' => __('Rooms in Makkah and Madinah at the distance shown with each package, with men and women in separate rooms.')],
            ['icon' => 'bus', 'title' => __('Air-conditioned buses'), 'text' => $hajj
                ? __('Jeddah–Makkah, Makkah–Madinah, Mina–Arafah–Muzdalifah and Madinah–Jeddah.')
                : __('Jeddah–Makkah, Makkah–Madinah and Madinah–airport.')],
            ['icon' => 'mosque', 'title' => __('Ziyarah with a scholar'), 'text' => $hajj
                ? __('Visits to the historic sites of Makkah and Madinah under an experienced scholar.')
                : __('More than 15 sites in Makkah and 10 in Madinah under an experienced scholar.')],
            ['icon' => 'utensils', 'title' => __('Bangladeshi meals'), 'text' => __('Three meals a day cooked by a Bangladeshi chef, on the packages that include meals.')],
        ];
    }

    /**
     * @return list<array{image: string, alt: string, icon: string, title: string, text: string}>
     */
    public function journey(Service $service): array
    {
        $hajj = $service->slug === 'hajj';

        return [
            ['image' => 'images/pilgrimage/preparation.webp', 'alt' => __('An open Quran'), 'icon' => 'book', 'title' => __('Before you fly'),
                'text' => $hajj
                    ? __('Workshops, a detailed guidebook and your ihram, so you know every rite before you arrive.')
                    : __('A discussion session and a pocket guidebook, so you know every rite before you arrive.')],
            ['image' => 'images/pilgrimage/makkah.webp', 'alt' => __('Pilgrims around the Kaaba in Masjid al-Haram'), 'icon' => 'kaaba', 'title' => __('Makkah'),
                'text' => $hajj
                    ? __('Tawaf and prayers in Masjid al-Haram, then the days of Mina, Arafah and Muzdalifah with your group.')
                    : __('Your Umrah and prayers in Masjid al-Haram, with a scholar to guide each step.')],
            ['image' => 'images/pilgrimage/madinah.webp', 'alt' => __('The Prophet\'s Mosque in Madinah at sunset'), 'icon' => 'mosque', 'title' => __('Madinah'),
                'text' => __('Prayers in Masjid an-Nabawi and visits to the historic sites of the city.')],
        ];
    }
}
