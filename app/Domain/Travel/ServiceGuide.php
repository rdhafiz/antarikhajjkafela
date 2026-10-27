<?php

namespace App\Domain\Travel;

use App\Models\Service;

/**
 * The fixed parts of the Hajj, Umrah and Tours service pages: what the
 * packages include (from the agency's own facility lists and tour plans) and
 * the journey photo cards. Other services have none.
 */
class ServiceGuide
{
    /**
     * @return array{included: array{title: string, intro: string, items: list<array{icon: string, title: string, text: string}>}, journey: array{title: string, intro: string, cards: list<array{image: string, alt: string, icon: string, title: string, text: string}>}}|null
     */
    public function sections(Service $service): ?array
    {
        return match (true) {
            $service->isPilgrimage() => $this->pilgrimage($service),
            $service->slug === 'tours' => $this->tours(),
            default => null,
        };
    }

    private function pilgrimage(Service $service): array
    {
        $hajj = $service->slug === 'hajj';
        $name = $service->getTranslation('name', app()->getLocale());

        return [
            'included' => [
                'title' => __('What your :name package includes', ['name' => $name]),
                'intro' => __('Every package is arranged by our licensed Hajj and Umrah division, Antarik Hajj Kafela.'),
                'items' => [
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
                ],
            ],
            'journey' => [
                'title' => __('From Dhaka to the two Holy Mosques'),
                'intro' => $hajj
                    ? __('Prepared before you leave, guided through every rite, and looked after until you are home.')
                    : __('Prepared before you leave, guided through your Umrah, and looked after until you are home.'),
                'cards' => [
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
                ],
            ],
        ];
    }

    private function tours(): array
    {
        return [
            'included' => [
                'title' => __('What your tour includes'),
                'intro' => __('Every tour starts and ends in Dhaka, with one team looking after you from the first quote to the flight home.'),
                'items' => [
                    ['icon' => 'plane', 'title' => __('Flights from Dhaka'), 'text' => __('Return flights booked with your tour, and domestic flights where the plan needs them.')],
                    ['icon' => 'hotel', 'title' => __('Hotels'), 'text' => __('Hotels chosen for their location and comfort, named in each tour\'s plan.')],
                    ['icon' => 'bus', 'title' => __('Transfers'), 'text' => __('A driver waiting when you land, and transport between the cities of your tour.')],
                    ['icon' => 'compass', 'title' => __('Sightseeing'), 'text' => __('A day-by-day plan with the sights of every stop, sent to you before you leave.')],
                    ['icon' => 'passport', 'title' => __('Visa support'), 'text' => __('We check your documents and prepare the visa file, or tell you when the visa is on arrival.')],
                    ['icon' => 'utensils', 'title' => __('Halal food noted'), 'text' => __('Halal food options noted for each city on your plan.')],
                ],
            ],
            'journey' => [
                'title' => __('From Dhaka and back, planned for you'),
                'intro' => __('One team plans the trip, books it and stays reachable on WhatsApp until you are home.'),
                'cards' => [
                    ['image' => 'images/tours/plan.webp', 'alt' => __('Passports ready for a visa application'), 'icon' => 'passport', 'title' => __('Before you fly'),
                        'text' => __('Your visa file prepared and a day-by-day plan in your hands before you leave.')],
                    ['image' => 'images/tours/flight.webp', 'alt' => __('An airliner lifting off the runway'), 'icon' => 'plane', 'title' => __('Flights and transfers'),
                        'text' => __('Flights from Dhaka, and a driver waiting when you land.')],
                    ['image' => 'images/tours/stay.webp', 'alt' => __('The Blue Mosque in Istanbul'), 'icon' => 'compass', 'title' => __('At your destination'),
                        'text' => __('Hotels, sightseeing and halal food options planned for each city.')],
                ],
            ],
        ];
    }
}
