<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentProcessor;
use App\PageBuilder\Field;
use App\PageBuilder\SectionStyle;
use App\Support\Locales;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PageSaveRequest extends FormRequest
{
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Page $page */
        $page = $this->route('page');
        $locales = app(Locales::class);
        $registry = app(ComponentRegistry::class);
        $processor = app(ContentProcessor::class);
        $default = $locales->default();

        $rules = [
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:160'],
            'title.*' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::in([Page::STATUS_DRAFT, Page::STATUS_PUBLISHED])],
            'seo' => ['nullable', 'array'],
            'seo.title.*' => ['nullable', 'string', 'max:70'],
            'seo.description.*' => ['nullable', 'string', 'max:200'],
            'seo.image' => ['nullable', 'integer', 'exists:media,id,deleted_at,NULL'],
            'seo.noindex' => ['nullable', 'boolean'],
            'sections' => ['present', 'array', 'max:40'],
            'sections.*' => ['array'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.component' => ['required', 'string', Rule::in(array_keys($registry->all()))],
            'sections.*.is_visible' => ['nullable', 'boolean'],
            'sections.*.style' => ['nullable', 'array'],
        ];

        if ($page->type !== Page::TYPE_DEFAULT) {
            $rules['slug'] = [
                'required', 'string', 'max:160', 'regex:'.self::SLUG_PATTERN,
                Rule::notIn(config('page-builder.reserved_slugs')),
                Rule::unique('pages', 'slug')->ignore($page->id),
                Rule::unique('services', 'slug'),
            ];
        }

        if ($page->isCampaign()) {
            $rules += [
                'starts_at' => ['nullable', 'date'],
                'expires_at' => ['nullable', 'date', 'after:starts_at'],
                'focused_layout' => ['nullable', 'boolean'],
                'tracking' => ['nullable', 'array'],
                'tracking.gtm_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]+$/'],
                'tracking.facebook_pixel_id' => ['nullable', 'regex:/^[0-9]{5,20}$/'],
            ];
        }

        // Content rules depend on each section's component. Required fields
        // are only enforced when the page goes live; a draft may be half done.
        $publishing = $this->input('status') === Page::STATUS_PUBLISHED;

        foreach ((array) $this->input('sections', []) as $index => $section) {
            $key = is_array($section) ? ($section['component'] ?? null) : null;

            if (is_string($key) && $registry->has($key)) {
                $rules = [...$rules, ...$processor->rules($registry->get($key)->fields(), "sections.{$index}.content", $publishing)];
            }

            foreach (SectionStyle::fields() as $field) {
                if ($field->type === Field::COLOR) {
                    $rules["sections.{$index}.style.{$field->key}"] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
                }
            }
            $rules["sections.{$index}.style.background_image"] = ['nullable', 'integer', 'exists:media,id,deleted_at,NULL'];
        }

        return $rules;
    }

    /**
     * Rules that need the whole section list.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Page $page */
            $page = $this->route('page');
            $registry = app(ComponentRegistry::class);
            $sections = collect((array) $this->input('sections', []))->filter(fn ($s) => is_array($s));
            $counts = $sections->countBy('component');

            foreach ($counts as $key => $count) {
                if (! $registry->has((string) $key)) {
                    continue;
                }

                $component = $registry->get((string) $key);

                if (! $component->repeatable() && $count > 1) {
                    $validator->errors()->add('sections', "Only one \"{$component->name()}\" section is allowed per page.");
                }

                if (! $component->isSystem() && ! $component->allowedOn($page->type)) {
                    $validator->errors()->add('sections', "\"{$component->name()}\" can't be used on this page.");
                }
            }

            $hasListing = config("page-builder.default_pages.{$page->system_key}.listing", false);

            if ($hasListing && ($counts['page_listing'] ?? 0) !== 1) {
                $validator->errors()->add('sections', 'The page listing section must stay on this page.');
            }

            if (! $hasListing && ($counts['page_listing'] ?? 0) > 0) {
                $validator->errors()->add('sections', 'This page has no listing section.');
            }
        }];
    }

    /**
     * Name every generated section path after the section and field an
     * editor actually sees, so the message points at something findable.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $locales = app(Locales::class);
        $registry = app(ComponentRegistry::class);
        $processor = app(ContentProcessor::class);

        $names = [
            'title' => 'Page title',
            "title.{$locales->default()}" => 'Page title ('.($locales->available()[$locales->default()]['name'] ?? $locales->default()).')',
            'slug' => 'Web address',
        ];

        foreach ((array) $this->input('sections', []) as $index => $section) {
            $key = is_array($section) ? ($section['component'] ?? null) : null;

            if (is_string($key) && $registry->has($key)) {
                $component = $registry->get($key);
                $names["sections.{$index}"] = $component->name();
                $names["sections.{$index}.content"] = $component->name();
                $names = [...$names, ...$processor->attributes($component->fields(), "sections.{$index}.content", $component->name())];
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only, e.g. hajj-registration-2027.',
            'slug.not_in' => 'This address is used by the website. Choose another.',
            'expires_at.after' => 'The expiry must be after the start date.',
        ];
    }
}
