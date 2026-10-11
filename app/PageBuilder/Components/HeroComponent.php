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
        return 'Large opening section with headline, buttons and an image or slider.';
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
        $static = ['split', 'centered'];

        return [
            Field::select('variant', 'Layout', [
                'split' => 'Text with image beside it',
                'centered' => 'Centered text over a background image',
                'slider' => 'Image slider',
            ]),
            Field::text('eyebrow', 'Eyebrow')->max(60)->showIf('variant', $static),
            Field::text('title', 'Headline')->max(120)->showIf('variant', $static),
            Field::text('highlight', 'Highlighted words')->max(80)->hint('Shown in gold on a second line.')->showIf('variant', $static),
            Field::textarea('text', 'Supporting text')->max(400)->rows(3)->showIf('variant', $static),
            Field::image('image', 'Image', 'hero')->showIf('variant', $static),
            Field::link('primary', 'Primary button')->showIf('variant', $static),
            Field::link('secondary', 'Secondary button')->showIf('variant', $static),
            Field::repeater('slides', 'Slides', [
                Field::image('image', 'Image', 'hero')->required(),
                Field::text('eyebrow', 'Eyebrow')->max(60),
                Field::text('title', 'Headline')->max(120)->required(),
                Field::textarea('text', 'Supporting text')->max(300)->rows(2),
                Field::link('link', 'Button'),
            ])->max(6)->itemLabel('title')->showIf('variant', ['slider']),
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
