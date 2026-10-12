<?php

namespace App\Domain\Travel;

use App\Models\Package;
use App\PageBuilder\Field;

/**
 * Field definitions for the repeatable parts of a package. They drive the
 * admin forms, validation and sanitizing, the same way page-builder
 * components do.
 */
class PackageSchema
{
    /**
     * @return list<Field>
     */
    public static function flights(): array
    {
        return [
            Field::repeater('flights', 'Flights', [
                Field::text('airline', 'Airline')->max(120)->required()->placeholder('Saudia Airlines'),
                Field::text('schedule', 'Schedule')->max(200)->placeholder('Departs Dhaka 8 April 2027 (approx.)'),
                Field::textarea('description', 'Details')->max(1000)->rows(2)->placeholder('Direct flight, 2×23 kg baggage'),
            ])->max(10)->itemLabel('airline'),
        ];
    }

    /**
     * Package types → pricing variants → hotel details.
     *
     * @return list<Field>
     */
    public static function types(): array
    {
        return [
            Field::repeater('types', 'Package types', [
                Field::text('code', 'Package ID')->notTranslatable()->max(40)->placeholder('HJ-PREMIUM')->hint('Internal reference code.'),
                Field::text('name', 'Name')->max(120)->required()->placeholder('Premium'),
                Field::textarea('short_description', 'Short description')->max(500)->rows(2),
                Field::repeater('prices', 'Pricing', [
                    Field::text('title', 'Title')->max(120)->required()->placeholder('4/5-bed shared room'),
                    Field::textarea('short_description', 'Short description')->max(400)->rows(2),
                    Field::number('price', 'Price (BDT)', 0, 100000000)->required(),
                    Field::select('unit', 'Price unit', Package::UNITS),
                    Field::repeater('hotels', 'Hotel details', [
                        Field::text('heading', 'Heading')->max(120)->required()->placeholder('Makkah'),
                        Field::text('subheading', 'Subheading')->max(160)->placeholder('Standard hotel · within 400 m of the Haram'),
                        Field::textarea('description', 'Description')->max(1000)->rows(2),
                    ])->max(6)->itemLabel('heading'),
                ])->min(1)->max(10)->itemLabel('title'),
            ])->max(10)->itemLabel('name'),
        ];
    }

    /**
     * Custom sections shown on the package page below the package details.
     *
     * @return list<Field>
     */
    public static function sections(): array
    {
        return [
            Field::repeater('sections', 'Page sections', [
                Field::select('layout', 'Layout', ['full' => 'Full width', 'two_column' => 'Two columns (text + image)']),
                Field::text('badge', 'Badge')->max(40)->placeholder('Included'),
                Field::text('heading', 'Heading')->max(160),
                Field::text('subheading', 'Subheading')->max(300),
                Field::blocks('blocks', 'Content'),
                Field::link('cta', 'Button'),
                Field::image('image', 'Image', 'content')->hint('Full width: background image. Two columns: shown beside the text.'),
                Field::select('image_position', 'Image side', ['right' => 'Right', 'left' => 'Left'])->showIf('layout', ['two_column']),
                Field::color('text_color', 'Text color')->hint('Leave empty to choose automatically.'),
                Field::color('background_color', 'Background color')->hint('With a background image this becomes the overlay tint.'),
            ])->max(20)->itemLabel('heading'),
        ];
    }
}
