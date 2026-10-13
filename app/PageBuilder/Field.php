<?php

namespace App\PageBuilder;

/**
 * Describes one editable field of a page-builder component. The same
 * definition drives the admin form, validation, sanitizing and rendering.
 *
 *   Field::text('title', 'Title')->required()->max(120)
 *   Field::repeater('items', 'Items', [Field::text('label', 'Label')])->max(8)
 */
class Field
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const RICHTEXT = 'richtext';

    public const IMAGE = 'image';

    public const LINK = 'link';

    public const SELECT = 'select';

    public const TOGGLE = 'toggle';

    public const NUMBER = 'number';

    public const COLOR = 'color';

    public const ICON = 'icon';

    public const YOUTUBE = 'youtube';

    public const DATETIME = 'datetime';

    public const REPEATER = 'repeater';

    public const BLOCKS = 'blocks';

    /** @var array<string, mixed> */
    private array $options = [];

    private function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
    ) {
        $this->options['translatable'] = in_array($type, [self::TEXT, self::TEXTAREA, self::RICHTEXT], true);
    }

    public static function text(string $key, string $label): self
    {
        return (new self($key, self::TEXT, $label))->max(200);
    }

    public static function textarea(string $key, string $label): self
    {
        return (new self($key, self::TEXTAREA, $label))->max(2000);
    }

    public static function richtext(string $key, string $label): self
    {
        return (new self($key, self::RICHTEXT, $label))->max(60000);
    }

    public static function image(string $key, string $label, string $usage = 'general'): self
    {
        return (new self($key, self::IMAGE, $label))->with('usage', $usage);
    }

    public static function link(string $key, string $label): self
    {
        return new self($key, self::LINK, $label);
    }

    /**
     * @param  array<string, string>  $options  value => label
     */
    public static function select(string $key, string $label, array $options): self
    {
        return (new self($key, self::SELECT, $label))->with('options', $options)->default(array_key_first($options));
    }

    public static function toggle(string $key, string $label): self
    {
        return (new self($key, self::TOGGLE, $label))->default(false);
    }

    public static function number(string $key, string $label, int $min = 0, int $max = 1000000): self
    {
        return (new self($key, self::NUMBER, $label))->with('min', $min)->with('max', $max);
    }

    public static function color(string $key, string $label): self
    {
        return new self($key, self::COLOR, $label);
    }

    public static function icon(string $key, string $label): self
    {
        return new self($key, self::ICON, $label);
    }

    public static function youtube(string $key, string $label): self
    {
        return new self($key, self::YOUTUBE, $label);
    }

    public static function datetime(string $key, string $label): self
    {
        return new self($key, self::DATETIME, $label);
    }

    /**
     * Sortable content blocks (text, table, list, image, video).
     */
    public static function blocks(string $key, string $label): self
    {
        return (new self($key, self::BLOCKS, $label))->default([])->max(40);
    }

    /**
     * @param  list<self>  $fields
     */
    public static function repeater(string $key, string $label, array $fields): self
    {
        return (new self($key, self::REPEATER, $label))->with('fields', $fields)->default([])->max(12);
    }

    public function required(bool $required = true): self
    {
        return $this->with('required', $required);
    }

    /**
     * Required unless the sibling field (in the same item) has a value.
     */
    public function requiredWithout(string $sibling): self
    {
        return $this->with('required_without', $sibling);
    }

    public function max(int $max): self
    {
        return $this->with('max', $max);
    }

    public function min(int $min): self
    {
        return $this->with('min', $min);
    }

    public function hint(string $hint): self
    {
        return $this->with('hint', $hint);
    }

    public function placeholder(string $placeholder): self
    {
        return $this->with('placeholder', $placeholder);
    }

    public function rows(int $rows): self
    {
        return $this->with('rows', $rows);
    }

    public function notTranslatable(): self
    {
        return $this->with('translatable', false);
    }

    public function default(mixed $value): self
    {
        return $this->with('default', $value);
    }

    /**
     * For repeaters: the child field whose value titles each item in the admin.
     */
    public function itemLabel(string $key): self
    {
        return $this->with('item_label', $key);
    }

    /**
     * Only show this field in the admin when another field has one of the given values.
     *
     * @param  list<string|bool>  $values
     */
    public function showIf(string $field, array $values): self
    {
        return $this->with('show_if', ['field' => $field, 'values' => $values]);
    }

    public function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function isTranslatable(): bool
    {
        return (bool) $this->options['translatable'];
    }

    /**
     * @return list<self>
     */
    public function children(): array
    {
        return $this->options['fields'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $options = $this->options;

        if (isset($options['fields'])) {
            $options['fields'] = array_map(fn (self $field) => $field->toArray(), $options['fields']);
        }

        return ['key' => $this->key, 'type' => $this->type, 'label' => $this->label, ...$options];
    }

    private function with(string $name, mixed $value): self
    {
        $this->options[$name] = $value;

        return $this;
    }
}
