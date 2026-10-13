<?php

namespace App\Domain\Settings;

use App\Models\Page;
use App\Support\Locales;

/**
 * Shapes the settings schema for the admin screens: resolves dynamic option
 * lists and drops server-only details such as validation rules.
 */
class SettingsPresenter
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Locales $locales,
    ) {}

    /**
     * @return list<array{label: string, groups: list<array<string, string>>}>
     */
    public function navigation(): array
    {
        return collect(config('settings.navigation'))->map(fn ($section) => [
            'label' => $section['label'],
            'groups' => collect($section['groups'])->map(fn ($key) => [
                'key' => $key,
                'label' => config("settings.groups.{$key}.label"),
                'description' => config("settings.groups.{$key}.description"),
                'icon' => config("settings.groups.{$key}.icon"),
                ...$this->summary($key),
            ])->all(),
        ])->all();
    }

    /**
     * Field names (for searching every setting from the hub) and how many fields have a value.
     *
     * @return array{fields: list<array{key: string, label: string, section: string|null}>, filled: int, total: int}
     */
    private function summary(string $group): array
    {
        $fields = config("settings.groups.{$group}.fields", []);
        $values = $this->settings->group($group);

        $filled = collect($fields)->keys()->filter(function (string $key) use ($values) {
            $value = $values[$key] ?? null;

            return is_array($value) ? array_filter($value, fn ($v) => $v !== null && $v !== '') !== [] : ($value !== null && $value !== '' && $value !== false);
        })->count();

        return [
            'fields' => collect($fields)->map(fn ($field, $key) => [
                'key' => $key,
                'label' => $field['label'] ?? str($key)->headline()->toString(),
                'section' => $field['section'] ?? null,
            ])->values()->all(),
            'filled' => $filled,
            'total' => count($fields),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $definition = $this->settings->definition($group);

        $fields = collect($definition['fields'])->map(fn ($field, $key) => array_filter([
            'key' => $key,
            'type' => $field['type'],
            'label' => $field['label'] ?? str($key)->headline()->toString(),
            'hint' => $field['hint'] ?? null,
            'section' => $field['section'] ?? null,
            'translatable' => $field['translatable'] ?? false,
            'readonly' => $field['readonly'] ?? false,
            'usage' => $field['usage'] ?? null,
            'options' => $this->options($field),
            // A select whose default is empty can be switched off.
            'clearable' => $field['type'] === 'select' && ($field['default'] ?? null) === null ? true : null,
        ], fn ($value) => $value !== null))->values();

        return [
            'key' => $group,
            'label' => $definition['label'],
            'description' => $definition['description'] ?? null,
            'fields' => $fields,
            'values' => $this->settings->group($group),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array{value: string|int, label: string}>|null
     */
    private function options(array $field): ?array
    {
        if ($field['type'] === 'timezone') {
            return collect(timezone_identifiers_list())->map(fn ($tz) => ['value' => $tz, 'label' => str_replace('_', ' ', $tz)])->all();
        }

        if (($field['type'] ?? null) !== 'select') {
            return null;
        }

        return match ($field['options']) {
            'locales' => collect($this->locales->available())
                ->map(fn ($l) => ['value' => $l['code'], 'label' => $l['name'] === $l['native'] ? $l['name'] : "{$l['name']} ({$l['native']})"])
                ->values()->all(),
            'pages' => $this->pageOptions(),
            default => collect($field['options'])->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
        };
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function pageOptions(): array
    {
        return Page::query()
            ->whereIn('type', [Page::TYPE_DEFAULT, Page::TYPE_CUSTOM])
            ->orderBy('type')
            ->get()
            ->map(fn (Page $page) => ['value' => $page->id, 'label' => $page->getTranslation('title', 'en').' ('.($page->isHome() ? '/' : '/'.$page->slug).')'])
            ->all();
    }
}
