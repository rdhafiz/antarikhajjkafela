<?php

namespace App\Domain\Settings;

use App\Support\Locales;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Builds validation rules for a settings group from its schema.
 */
class SettingsValidator
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Locales $locales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function rules(string $group): array
    {
        $rules = [];

        foreach ($this->settings->schema($group) as $key => $field) {
            if ($field['readonly'] ?? false) {
                continue;
            }

            $base = array_merge(['nullable'], $this->typeRules($field), $field['rules'] ?? []);
            $base = array_values(array_unique($base, SORT_REGULAR));

            if ($field['translatable'] ?? false) {
                $rules[$key] = ['nullable', 'array'];

                foreach ($this->locales->codes() as $locale) {
                    $rules["{$key}.{$locale}"] = $base;
                }

                continue;
            }

            $rules[$key] = $base;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    private function typeRules(array $field): array
    {
        return match ($field['type']) {
            'string' => ['string', 'max:255'],
            // Any country's number; numbers without a code are read as Bangladeshi.
            'phone' => ['string', 'max:40', (new Phone)->international()->country('BD')],
            'text' => ['string', 'max:2000'],
            'json' => ['string', 'json'],
            'map' => ['url:https', 'max:2000', 'regex:#^https://(www\.)?google\.com/maps/embed\?#'],
            'secret' => ['string', 'max:500'],
            'email' => ['email', 'max:190'],
            'url' => ['url:http,https', 'max:255'],
            'color' => ['regex:/^#[0-9a-fA-F]{6}$/'],
            'boolean' => ['boolean'],
            'media' => ['integer', Rule::exists('media', 'id')],
            'timezone' => ['timezone:all'],
            'select' => $this->selectRules($field),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<mixed>
     */
    private function selectRules(array $field): array
    {
        return match ($field['options']) {
            'locales' => [Rule::in($this->locales->codes())],
            'pages' => ['integer', Rule::exists('pages', 'id')],
            default => [Rule::in(array_keys($field['options']))],
        };
    }
}
