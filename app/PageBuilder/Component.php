<?php

namespace App\PageBuilder;

use App\Models\Page;
use App\Models\PageSection;

/**
 * A reusable page section design. The same component can be placed on any
 * number of pages; each placement (PageSection) has its own content and style.
 */
abstract class Component
{
    /** Unique key stored on page sections; also the Blade view name. */
    abstract public function key(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * @return list<Field>
     */
    abstract public function fields(): array;

    /** Admin picker group: hero, content, conversion, media, dynamic. */
    public function category(): string
    {
        return 'content';
    }

    /** Lucide icon name shown in the admin component picker. */
    public function icon(): string
    {
        return 'square';
    }

    /**
     * Default content when the component is first added to a page.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $field) {
            $defaults[$field->key] = $field->option('default');
        }

        return $defaults;
    }

    /**
     * Default section style (background, spacing) for this component.
     *
     * @return array<string, mixed>
     */
    public function defaultStyle(): array
    {
        return ['background' => 'default', 'spacing' => 'lg'];
    }

    /** Hidden from the picker (system sections such as listing slots). */
    public function isSystem(): bool
    {
        return false;
    }

    /** Whether the component may be added to a page of this type. */
    public function allowedOn(string $pageType): bool
    {
        return true;
    }

    /**
     * The button shown at the bottom of the section on campaign pages: the key
     * of the component's own link field, or "cta" (added on campaign pages
     * when the component has none). Null for components with buttons of their
     * own (hero, call to action, image with text).
     */
    public function bottomButton(): ?string
    {
        return 'cta';
    }

    /**
     * The fields for a section on a page of this type. Campaign pages add the
     * bottom button (when the component has no button of its own) and its alignment.
     *
     * @return list<Field>
     */
    public function fieldsFor(?string $pageType): array
    {
        $fields = $this->fields();
        $key = $this->bottomButton();

        if ($pageType !== Page::TYPE_CAMPAIGN || $key === null) {
            return $fields;
        }

        $align = Field::select("{$key}_align", 'Button alignment', ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])->default('center');
        $at = collect($fields)->search(fn (Field $field) => $field->key === $key);

        if ($at === false) {
            return [...$fields, Field::link($key, 'Button')->hint('Shown at the bottom of the section. Leave the button text empty to hide it.'), $align];
        }

        array_splice($fields, $at + 1, 0, [$align->hint('The button is shown at the bottom of the section.')]);

        return $fields;
    }

    /** Whether more than one instance may exist on a page. */
    public function repeatable(): bool
    {
        return true;
    }

    public function view(): string
    {
        return 'sections.'.str_replace('_', '-', $this->key());
    }

    /**
     * Extra data for the view (e.g. records from other modules).
     *
     * @param  array<string, mixed>  $content  localized content
     * @return array<string, mixed>
     */
    public function data(array $content, PageSection $section, Page $page): array
    {
        return [];
    }

    /**
     * Structured data (JSON-LD) contributed to the page, if any.
     *
     * @param  array<string, mixed>  $content  localized content
     * @return array<string, mixed>|null
     */
    public function schema(array $content, Page $page): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(?string $pageType = null): array
    {
        $fields = $this->fieldsFor($pageType);
        $added = array_filter($fields, fn (Field $field) => ! array_key_exists($field->key, $this->defaults()));

        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'description' => $this->description(),
            'category' => $this->category(),
            'icon' => $this->icon(),
            'repeatable' => $this->repeatable(),
            'fields' => array_map(fn (Field $field) => $field->toArray(), $fields),
            'defaults' => [...$this->defaults(), ...collect($added)->mapWithKeys(fn (Field $field) => [$field->key => $field->option('default')])->all()],
            'default_style' => $this->defaultStyle(),
        ];
    }
}
