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
        return 'Large opening section: text beside an image, or one or more full-width slides (image with text, or image only).';
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
        $split = ['split'];
        $text = ['text'];

        return [
            Field::select('variant', 'Layout', [
                'split' => 'Text with image beside it',
                'centered' => 'Slides: full-width images, with or without text',
            ]),
            Field::text('eyebrow', 'Eyebrow')->max(60)->showIf('variant', $split),
            Field::text('title', 'Headline')->max(120)->showIf('variant', $split),
            Field::text('highlight', 'Highlighted words')->max(80)->hint('Shown in gold on a second line.')->showIf('variant', $split),
            Field::textarea('text', 'Supporting text')->max(400)->rows(3)->showIf('variant', $split),
            Field::image('image', 'Image', 'hero')->showIf('variant', $split),
            Field::link('primary', 'Primary button')->showIf('variant', $split),
            Field::link('secondary', 'Secondary button')->showIf('variant', $split),
            // One slide shows on its own; two or more play as a slider.
            Field::repeater('slides', 'Slides', [
                Field::select('type', 'Slide type', [
                    'text' => 'Image with text',
                    'image' => 'Image only (shown in full)',
                ])->default('text'),
                Field::image('image', 'Image', 'hero')->hint('Text slides without an image use the brand background. On image-only slides: the wide (horizontal) image for computers and tablets.'),
                Field::image('image_mobile', 'Mobile image (vertical)', 'hero')->hint('Optional. Shown on phones; if only one of the two images is set, it is used on every screen.')->showIf('type', ['image']),
                Field::text('eyebrow', 'Eyebrow')->max(60)->showIf('type', $text),
                Field::text('title', 'Headline')->max(120)->showIf('type', $text),
                Field::text('highlight', 'Highlighted words')->max(80)->showIf('type', $text),
                Field::textarea('text', 'Supporting text')->max(400)->rows(2)->showIf('type', $text),
                Field::link('primary', 'Primary button')->showIf('type', $text),
                Field::link('secondary', 'Secondary button')->showIf('type', $text),
                Field::link('link', 'Link (optional)')->hint('Makes the whole image clickable.')->showIf('type', ['image']),
            ])->max(8)->itemLabel('title')->hint('Each slide is an image with text or an image only. One slide shows on its own; with two or more they change every few seconds.')->showIf('variant', ['centered']),
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
