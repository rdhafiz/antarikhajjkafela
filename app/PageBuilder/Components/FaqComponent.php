<?php

namespace App\PageBuilder\Components;

use App\Models\Page;
use App\PageBuilder\Component;
use App\PageBuilder\Field;

class FaqComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'faq';
    }

    public function name(): string
    {
        return 'FAQ';
    }

    public function description(): string
    {
        return 'Frequently asked questions as an accordion. Also shown as rich results in Google.';
    }

    public function icon(): string
    {
        return 'circle-help';
    }

    public function fields(): array
    {
        return [
            ...$this->headingFields(),
            Field::select('layout', 'Layout', ['split' => 'Heading beside the questions', 'stacked' => 'Heading above the questions']),
            Field::repeater('items', 'Questions', [
                Field::text('question', 'Question')->max(200)->required(),
                Field::richtext('answer', 'Answer')->max(5000)->required(),
            ])->min(1)->max(30)->itemLabel('question'),
            Field::link('cta', 'Button'),
        ];
    }

    public function schema(array $content, Page $page): ?array
    {
        $items = array_filter($content['items'] ?? [], fn ($item) => filled($item['question']) && filled($item['answer']));

        if ($items === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_values(array_map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($item['answer'])],
            ], $items)),
        ];
    }
}
