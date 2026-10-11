<?php

namespace App\PageBuilder;

use InvalidArgumentException;

/**
 * All page-builder components, from config('page-builder.components').
 * Modules built later register their components by adding them there.
 */
class ComponentRegistry
{
    /** @var array<string, Component>|null */
    private ?array $components = null;

    /**
     * @return array<string, Component>
     */
    public function all(): array
    {
        if ($this->components === null) {
            $this->components = [];

            foreach (config('page-builder.components', []) as $class) {
                $component = app($class);
                $this->components[$component->key()] = $component;
            }
        }

        return $this->components;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): Component
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown page component [{$key}].");
    }

    /**
     * Components an admin can add to a page of the given type.
     *
     * @return list<Component>
     */
    public function pickable(string $pageType): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (Component $component) => ! $component->isSystem() && $component->allowedOn($pageType),
        ));
    }
}
