<?php

namespace App\PageBuilder;

use App\Models\Media;
use App\Support\Icons;
use App\Support\Locales;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Works through component content using the field definitions:
 * builds validation rules, sanitizes input before saving, collects media
 * ids, and localizes stored content for rendering.
 */
class ContentProcessor
{
    /** Link targets allowed in buttons and links. */
    public const LINK_PATTERN = '#^(https?://|/|mailto:|tel:|\#)#i';

    public const YOUTUBE_PATTERN = '#^https://(www\.)?(youtube\.com/(watch\?v=|embed/|shorts/)|youtu\.be/)[\w-]{6,}#i';

    public function __construct(
        private readonly Locales $locales,
        private readonly ContentBlocks $blocks,
    ) {}

    /**
     * Validation rules for content stored under $prefix (e.g. "sections.3.content").
     *
     * $requireContent off lets a half-written draft save: a new page starts
     * with empty sections, and nobody should have to finish them in one go.
     * Required fields are enforced when the page is published.
     *
     * @param  list<Field>  $fields
     * @return array<string, mixed>
     */
    public function rules(array $fields, string $prefix, bool $requireContent = true): array
    {
        $rules = [$prefix => ['nullable', 'array']];
        $requiredLocale = $this->locales->default();

        foreach ($fields as $field) {
            $path = "{$prefix}.{$field->key}";
            $required = $requireContent && (bool) $field->option('required');

            switch ($field->type) {
                case Field::TEXT:
                case Field::TEXTAREA:
                case Field::RICHTEXT:
                    $base = ['nullable', 'string', 'max:'.$field->option('max')];

                    if ($field->isTranslatable()) {
                        // Required-ness lives on the default locale below, so
                        // one empty field reports one error, not two.
                        $rules[$path] = ['nullable', 'array'];
                        foreach ($this->locales->codes() as $locale) {
                            $rules["{$path}.{$locale}"] = $base;
                        }
                        if ($required) {
                            $rules["{$path}.{$requiredLocale}"] = ['required', ...array_slice($base, 1)];
                        }
                    } else {
                        $rules[$path] = $required ? ['required', ...array_slice($base, 1)] : $base;
                    }
                    break;

                case Field::IMAGE:
                    $sibling = $field->option('required_without');
                    $rules[$path] = $sibling
                        ? ["required_without:{$prefix}.{$sibling}", 'nullable', 'integer', 'exists:media,id,deleted_at,NULL']
                        : [$required ? 'required' : 'nullable', 'integer', 'exists:media,id,deleted_at,NULL'];
                    break;

                case Field::LINK:
                    $rules[$path] = ['nullable', 'array'];
                    $rules["{$path}.url"] = [$required ? 'required' : 'nullable', 'string', 'max:500', 'regex:'.self::LINK_PATTERN];
                    foreach ($this->locales->codes() as $locale) {
                        $rules["{$path}.label.{$locale}"] = ['nullable', 'string', 'max:80'];
                    }
                    break;

                case Field::SELECT:
                    $rules[$path] = [$required ? 'required' : 'nullable', 'in:'.implode(',', array_keys($field->option('options', [])))];
                    break;

                case Field::TOGGLE:
                    $rules[$path] = ['nullable', 'boolean'];
                    break;

                case Field::NUMBER:
                    $rules[$path] = [$required ? 'required' : 'nullable', 'integer', 'min:'.$field->option('min', 0), 'max:'.$field->option('max', 1000000)];
                    break;

                case Field::COLOR:
                    $rules[$path] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
                    break;

                case Field::ICON:
                    $rules[$path] = ['nullable', 'in:'.implode(',', Icons::CONTENT)];
                    break;

                case Field::YOUTUBE:
                    $rules[$path] = [$required ? 'required' : 'nullable', 'string', 'max:300', 'regex:'.self::YOUTUBE_PATTERN];
                    break;

                case Field::DATETIME:
                    $rules[$path] = [$required ? 'required' : 'nullable', 'date'];
                    break;

                case Field::BLOCKS:
                    $rules = [...$rules, ...$this->blocks->rules($path, $field->option('max', 40))];
                    break;

                case Field::REPEATER:
                    $rules[$path] = [$required ? 'required' : 'nullable', 'array', 'max:'.$field->option('max', 12)];
                    if ($requireContent && $field->option('min')) {
                        $rules[$path][] = 'min:'.$field->option('min');
                    }
                    $rules = [...$rules, ...$this->rules($field->children(), "{$path}.*", $requireContent)];
                    unset($rules["{$path}.*"]);
                    $rules["{$path}.*"] = ['array'];
                    $rules["{$path}.*._id"] = ['nullable', 'string', 'max:40'];
                    break;
            }
        }

        return $rules;
    }

    /**
     * Readable names for the paths rules() generates, so a failed save says
     * "Text → Body (Bangla)" instead of "sections.3.content.body.bn".
     *
     * @param  list<Field>  $fields
     * @return array<string, string>
     */
    public function attributes(array $fields, string $prefix, string $section): array
    {
        $names = [];

        foreach ($fields as $field) {
            $path = "{$prefix}.{$field->key}";
            $label = "{$section} → {$field->label}";
            $names[$path] = $label;

            switch ($field->type) {
                case Field::TEXT:
                case Field::TEXTAREA:
                case Field::RICHTEXT:
                    if ($field->isTranslatable()) {
                        foreach ($this->locales->available() as $code => $locale) {
                            $names["{$path}.{$code}"] = "{$label} ({$locale['name']})";
                        }
                    }
                    break;

                case Field::LINK:
                    $names["{$path}.url"] = "{$label} address";
                    foreach ($this->locales->available() as $code => $locale) {
                        $names["{$path}.label.{$code}"] = "{$label} text ({$locale['name']})";
                    }
                    break;

                case Field::REPEATER:
                    $names = [...$names, ...$this->attributes($field->children(), "{$path}.*", $section)];
                    break;
            }
        }

        return $names;
    }

    /**
     * Keep only defined fields, normalize their types and purify rich text.
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function sanitize(array $fields, ?array $data): array
    {
        $data ??= [];
        $clean = [];

        foreach ($fields as $field) {
            $value = $data[$field->key] ?? null;

            $clean[$field->key] = match ($field->type) {
                Field::TEXT, Field::TEXTAREA, Field::RICHTEXT => $field->isTranslatable()
                    ? $this->sanitizeTranslations($field, is_array($value) ? $value : [])
                    : $this->sanitizeString($field, $value),
                Field::IMAGE, Field::NUMBER => $value === null || $value === '' ? null : (int) $value,
                Field::TOGGLE => (bool) $value,
                Field::LINK => [
                    'label' => $this->sanitizeTranslations(Field::text('label', 'Label'), $value['label'] ?? []),
                    'url' => filled($value['url'] ?? null) ? trim($value['url']) : null,
                ],
                Field::REPEATER => collect(is_array($value) ? $value : [])
                    ->filter(fn ($item) => is_array($item))
                    ->map(fn ($item) => ['_id' => $this->itemId($item), ...$this->sanitize($field->children(), $item)])
                    ->values()
                    ->all(),
                Field::BLOCKS => $this->blocks->sanitize($value),
                default => filled($value) ? $value : null,
            };
        }

        return $clean;
    }

    /**
     * Media ids referenced by content (to load them in one query).
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>|null  $data
     * @return list<int>
     */
    public function mediaIds(array $fields, ?array $data): array
    {
        $ids = [];

        foreach ($fields as $field) {
            $value = $data[$field->key] ?? null;

            if ($field->type === Field::IMAGE && $value) {
                $ids[] = (int) $value;
            }

            if ($field->type === Field::REPEATER && is_array($value)) {
                foreach ($value as $item) {
                    array_push($ids, ...$this->mediaIds($field->children(), $item));
                }
            }

            if ($field->type === Field::BLOCKS) {
                array_push($ids, ...$this->blocks->mediaIds($value));
            }
        }

        return $ids;
    }

    /**
     * Content in one language, ready for a Blade view: translations resolved,
     * images replaced by Media models, links by ['label', 'url'].
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>|null  $data
     * @param  Collection<int, Media>  $media
     * @return array<string, mixed>
     */
    public function localize(array $fields, ?array $data, string $locale, Collection $media): array
    {
        $data ??= [];
        $result = [];

        foreach ($fields as $field) {
            $value = $data[$field->key] ?? $field->option('default');

            $result[$field->key] = match ($field->type) {
                Field::TEXT, Field::TEXTAREA, Field::RICHTEXT => $field->isTranslatable() ? $this->translate($value, $locale) : $value,
                Field::IMAGE => $value ? $media->get((int) $value) : null,
                Field::LINK => filled($value['url'] ?? null) ? [
                    'label' => $this->translate($value['label'] ?? [], $locale),
                    'url' => $this->absoluteUrl($value['url']),
                    'external' => str_starts_with($value['url'], 'http') && ! str_starts_with($value['url'], url('/')),
                ] : null,
                Field::REPEATER => array_map(
                    fn ($item) => $this->localize($field->children(), $item, $locale, $media),
                    is_array($value) ? $value : [],
                ),
                Field::BLOCKS => $this->blocks->localize($value, $locale, $media, $this),
                default => $value,
            };
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|string|null  $value
     */
    public function translate(mixed $value, string $locale): ?string
    {
        if (! is_array($value)) {
            return $value;
        }

        $fallback = $this->locales->default();

        $text = $value[$locale] ?? null;

        return filled($text) ? $text : (filled($value[$fallback] ?? null) ? $value[$fallback] : collect($value)->first(fn ($v) => filled($v)));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, string|null>
     */
    private function sanitizeTranslations(Field $field, array $value): array
    {
        $result = [];

        foreach ($this->locales->codes() as $locale) {
            $result[$locale] = $this->sanitizeString($field, $value[$locale] ?? null);
        }

        return $result;
    }

    private function sanitizeString(Field $field, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if ($field->type === Field::RICHTEXT) {
            $html = trim(Purifier::clean($value, 'rich_text'));

            return $html === '' || $html === '<p></p>' ? null : $html;
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemId(array $item): string
    {
        $id = $item['_id'] ?? null;

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{4,40}$/', $id) ? $id : Str::random(10);
    }

    private function absoluteUrl(string $url): string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }
}
