<?php

namespace App\PageBuilder\Components;

use App\PageBuilder\Component;
use App\PageBuilder\Field;

class VideoComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'video';
    }

    public function name(): string
    {
        return 'Video';
    }

    public function description(): string
    {
        return 'A YouTube video that loads only when played, keeping the page fast.';
    }

    public function category(): string
    {
        return 'media';
    }

    public function icon(): string
    {
        return 'circle-play';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::youtube('url', 'YouTube link')->required()->placeholder('https://www.youtube.com/watch?v=…'),
            Field::image('poster', 'Cover image', 'content')->hint('Optional. The video thumbnail is used if empty.'),
            Field::select('layout', 'Layout', ['centered' => 'Centered', 'split' => 'Video beside the text']),
        ];
    }
}
