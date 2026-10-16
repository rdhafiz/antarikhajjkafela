<?php

namespace App\PageBuilder;

use App\Models\Media;
use App\Support\Locales;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Free-flowing content made of sortable blocks, used by package sections
 * and blog posts. Block types:
 *
 *   text   ['body' => {locale: html}]
 *   table  ['header' => bool, 'rows' => [[{locale: text}, ...], ...], 'caption' => {locale: text}]
 *   list   ['style' => bullet|check|number, 'items' => [{locale: text}, ...]]
 *   image  ['image' => media id, 'caption' => {locale: text}]
 *   video  ['url' => YouTube URL, 'caption' => {locale: text}]
 */
class ContentBlocks
{
    public const TYPES = ['text', 'table', 'list', 'image', 'video'];

    public const LIST_STYLES = ['bullet', 'check', 'number'];

    public function __construct(private readonly Locales $locales) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(string $path, int $max = 40): array
    {
        $rules = [
            $path => ['nullable', 'array', "max:{$max}"],
            "{$path}.*" => ['array'],
            "{$path}.*._id" => ['nullable', 'string', 'max:40'],
            "{$path}.*.type" => ['required', 'in:'.implode(',', self::TYPES)],
            "{$path}.*.header" => ['nullable', 'boolean'],
            "{$path}.*.rows" => ['nullable', 'array', 'max:40'],
            "{$path}.*.rows.*" => ['array', 'max:8'],
            "{$path}.*.style" => ['nullable', 'in:'.implode(',', self::LIST_STYLES)],
            "{$path}.*.items" => ['nullable', 'array', 'max:50'],
            "{$path}.*.image" => ['nullable', 'integer', 'exists:media,id,deleted_at,NULL'],
            "{$path}.*.url" => ['nullable', 'string', 'max:300', 'regex:'.ContentProcessor::YOUTUBE_PATTERN],
        ];

        foreach ($this->locales->codes() as $locale) {
            $rules["{$path}.*.body.{$locale}"] = ['nullable', 'string', 'max:30000'];
            $rules["{$path}.*.caption.{$locale}"] = ['nullable', 'string', 'max:300'];
            $rules["{$path}.*.rows.*.*.{$locale}"] = ['nullable', 'string', 'max:600'];
            $rules["{$path}.*.items.*.{$locale}"] = ['nullable', 'string', 'max:400'];
        }

        return $rules;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sanitize(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $clean = [];

        foreach ($blocks as $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;

            if (! in_array($type, self::TYPES, true)) {
                continue;
            }

            $id = is_string($block['_id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{4,40}$/', $block['_id']) ? $block['_id'] : Str::random(10);
            $base = ['_id' => $id, 'type' => $type];

            $clean[] = match ($type) {
                'text' => $base + ['body' => $this->translations($block['body'] ?? [], html: true)],
                'table' => $base + [
                    'header' => (bool) ($block['header'] ?? true),
                    'rows' => array_values(array_map(
                        fn ($row) => array_values(array_map(fn ($cell) => $this->translations(is_array($cell) ? $cell : []), array_slice(is_array($row) ? $row : [], 0, 8))),
                        array_slice(is_array($block['rows'] ?? null) ? $block['rows'] : [], 0, 40),
                    )),
                    'caption' => $this->translations($block['caption'] ?? []),
                ],
                'list' => $base + [
                    'style' => in_array($block['style'] ?? null, self::LIST_STYLES, true) ? $block['style'] : 'bullet',
                    'items' => array_values(array_map(fn ($item) => $this->translations(is_array($item) ? $item : []), array_slice(is_array($block['items'] ?? null) ? $block['items'] : [], 0, 50))),
                ],
                'image' => $base + ['image' => ! empty($block['image']) ? (int) $block['image'] : null, 'caption' => $this->translations($block['caption'] ?? [])],
                'video' => $base + ['url' => filled($block['url'] ?? null) ? trim($block['url']) : null, 'caption' => $this->translations($block['caption'] ?? [])],
            };
        }

        return $clean;
    }

    /**
     * @return list<int>
     */
    public function mediaIds(mixed $blocks): array
    {
        return collect(is_array($blocks) ? $blocks : [])
            ->where('type', 'image')
            ->pluck('image')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Blocks in one language, for rendering.
     *
     * @param  Collection<int, Media>  $media
     * @return list<array<string, mixed>>
     */
    public function localize(mixed $blocks, string $locale, Collection $media, ContentProcessor $processor): array
    {
        $result = [];

        foreach (is_array($blocks) ? $blocks : [] as $block) {
            $item = match ($block['type'] ?? null) {
                'text' => filled($body = $processor->translate($block['body'] ?? [], $locale)) ? ['type' => 'text', 'body' => $body] : null,
                'table' => ($rows = array_map(fn ($row) => array_map(fn ($cell) => (string) $processor->translate($cell, $locale), $row), $block['rows'] ?? [])) !== []
                    ? ['type' => 'table', 'header' => (bool) ($block['header'] ?? true), 'rows' => $rows, 'caption' => $processor->translate($block['caption'] ?? [], $locale)]
                    : null,
                'list' => ($items = array_values(array_filter(array_map(fn ($i) => $processor->translate($i, $locale), $block['items'] ?? []), 'filled'))) !== []
                    ? ['type' => 'list', 'style' => $block['style'] ?? 'bullet', 'items' => $items]
                    : null,
                'image' => ($image = $media->get((int) ($block['image'] ?? 0))) ? ['type' => 'image', 'image' => $image, 'caption' => $processor->translate($block['caption'] ?? [], $locale)] : null,
                'video' => filled($block['url'] ?? null) ? ['type' => 'video', 'url' => $block['url'], 'caption' => $processor->translate($block['caption'] ?? [], $locale)] : null,
                default => null,
            };

            if ($item !== null) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @return array<string, string|null>
     */
    private function translations(mixed $value, bool $html = false): array
    {
        $value = is_array($value) ? $value : [];
        $result = [];

        foreach ($this->locales->codes() as $locale) {
            $text = $value[$locale] ?? null;

            if (! is_string($text) || trim($text) === '') {
                $result[$locale] = null;

                continue;
            }

            if ($html) {
                $text = trim(Purifier::clean($text, 'rich_text'));
                $text = $text === '' || $text === '<p></p>' ? null : $text;
            } else {
                $text = trim($text);
            }

            $result[$locale] = $text;
        }

        return $result;
    }
}
