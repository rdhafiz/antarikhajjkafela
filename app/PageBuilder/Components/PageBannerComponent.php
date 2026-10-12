<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class PageBannerComponent extends Component
{
    public function key(): string
    {
        return 'page_banner';
    }

    public function name(): string
    {
        return 'Page banner';
    }

    public function description(): string
    {
        return 'Centered hero for inner pages: eyebrow, title with an optional gold highlight line, introduction and background image.';
    }

    public function category(): string
    {
        return 'hero';
    }

    public function icon(): string
    {
        return 'rectangle-horizontal';
    }

    public function repeatable(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::text('eyebrow', 'Eyebrow')->max(60),
            Field::text('title', 'Title')->max(120)->hint('Leave empty to use the page title.'),
            Field::text('highlight', 'Highlight line')->max(90)->hint('Optional second line in gold italics under the title.'),
            Field::textarea('intro', 'Introduction')->max(400)->rows(3),
            Field::image('image', 'Background image', 'banner')->hint('Leave empty to use the default banner from the settings.'),
        ];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'navy-deep', 'spacing' => 'none'];
    }
}
