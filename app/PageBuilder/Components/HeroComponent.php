<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class HeroComponent extends Component
{
    public function key(): string
    {
        return 'hero';
    }

    public function name(): string
    {
        return 'Hero';
    }

    public function description(): string
    {
        return 'Large opening section with headline, buttons and an image; add slides to make it a slider.';
    }

    public function category(): string
    {
        return 'hero';
    }

    public function icon(): string
    {
        return 'panel-top';
    }

    public function fields(): array
    {
        $text = ['text'];

        return [
            Field::select('variant', 'Layout', [
                'split' => 'Text with image beside it',
                'centered' => 'Centered text over a background image',
            ]),
            Field::text('eyebrow', 'Eyebrow')->max(60),
            Field::text('title', 'Headline')->max(120),
            Field::text('highlight', 'Highlighted words')->max(80)->hint('Shown in gold on a second line.'),
            Field::textarea('text', 'Supporting text')->max(400)->rows(3),
            Field::image('image', 'Image', 'hero'),
            Field::link('primary', 'Primary button'),
            Field::link('secondary', 'Secondary button'),
            // The hero above is the first slide; adding slides turns it into a slider.
            Field::repeater('slides', 'Extra slides', [
                Field::select('type', 'Slide type', [
                    'text' => 'Image with text',
                    'image' => 'Image only (shown in full)',
                ])->default('text'),
                Field::image('image', 'Image', 'hero')->requiredWithout('image_mobile')->hint('On image-only slides: the wide (horizontal) image for computers and tablets.'),
                Field::image('image_mobile', 'Mobile image (vertical)', 'hero')->hint('Optional. Shown on phones; if only one of the two images is set, it is used on every screen.')->showIf('type', ['image']),
                Field::text('eyebrow', 'Eyebrow')->max(60)->showIf('type', $text),
                Field::text('title', 'Headline')->max(120)->showIf('type', $text),
                Field::text('highlight', 'Highlighted words')->max(80)->showIf('type', $text),
                Field::textarea('text', 'Supporting text')->max(400)->rows(2)->showIf('type', $text),
                Field::link('primary', 'Primary button')->showIf('type', $text),
                Field::link('secondary', 'Secondary button')->showIf('type', $text),
                Field::link('link', 'Link (optional)')->hint('Makes the whole image clickable.')->showIf('type', ['image']),
            ])->max(6)->itemLabel('title')->hint('The hero above is the first slide. Add slides to turn it into a slider that changes every few seconds.')->showIf('variant', ['centered']),
            Field::repeater('highlights', 'Trust figures', [
                Field::text('value', 'Value')->notTranslatable()->max(12)->placeholder('1500+'),
                Field::text('label', 'Label')->max(40),
            ])->max(4)->itemLabel('label')->hint('Optional strip of key numbers under the hero.'),
        ];
    }

    public function defaults(): array
    {
        return [
            ...parent::defaults(),
            'title' => ['en' => 'A sacred journey,', 'bn' => 'একটি পবিত্র যাত্রা,'],
            'highlight' => ['en' => 'guided with care.', 'bn' => 'যত্নের সাথে পরিচালিত।'],
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'navy-deep', 'spacing' => 'none'];
    }
}
