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
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'description' => $this->description(),
            'category' => $this->category(),
            'icon' => $this->icon(),
            'repeatable' => $this->repeatable(),
            'fields' => array_map(fn (Field $field) => $field->toArray(), $this->fields()),
            'defaults' => $this->defaults(),
            'default_style' => $this->defaultStyle(),
        ];
    }
}
