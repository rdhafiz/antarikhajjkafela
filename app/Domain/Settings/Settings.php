<?php

namespace App\Domain\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Read and write access to the settings declared in config/settings.php.
 *
 * Paths use "group.key" notation, e.g. settings()->get('theme.primary_color').
 */
class Settings
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $stored = null;

    public function __construct(private readonly Cache $cache) {}

    public function get(string $path, mixed $default = null): mixed
    {
        [$group, $key] = $this->split($path);
        $field = $this->field($group, $key);

        $stored = $this->stored();

        if (! array_key_exists($group, $stored) || ! array_key_exists($key, $stored[$group])) {
            return $field['default'] ?? $default;
        }

        $value = $stored[$group][$key];

        return ($field['type'] ?? null) === 'secret' ? $this->decrypt($value) : $value;
    }

    /**
     * Resolve a translatable setting for the given (or current) locale,
     * falling back to the default language and then to any filled value.
     */
    public function translated(string $path, ?string $locale = null): ?string
    {
        $value = $this->get($path);

        if (! is_array($value)) {
            return $value;
        }

        $locale ??= app()->getLocale();

        return $value[$locale]
            ?? $value[config('app.fallback_locale')]
            ?? collect($value)->first(fn ($v) => filled($v));
    }

    /**
     * All values of a group, with secrets masked unless $revealSecrets is true.
     *
     * @return array<string, mixed>
     */
    public function group(string $group, bool $revealSecrets = false): array
    {
        $values = [];

        foreach ($this->schema($group) as $key => $field) {
            $value = $this->get("{$group}.{$key}");

            if (($field['type'] ?? null) === 'secret' && ! $revealSecrets) {
                $value = filled($value);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Persist values for one group. Unknown keys and readonly fields are ignored,
     * and an empty secret keeps the currently stored secret.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(string $group, array $values): void
    {
        $schema = $this->schema($group);

        DB::transaction(function () use ($group, $values, $schema) {
            foreach ($values as $key => $value) {
                $field = $schema[$key] ?? null;

                if ($field === null || ($field['readonly'] ?? false)) {
                    continue;
                }

                if (($field['type'] ?? null) === 'secret') {
                    if (blank($value)) {
                        continue;
                    }

                    $value = Crypt::encryptString((string) $value);
                }

                Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
            }
        });

        $this->flush();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function schema(?string $group = null): array
    {
        $groups = config('settings.groups', []);

        if ($group === null) {
            return $groups;
        }

        if (! array_key_exists($group, $groups)) {
            throw new InvalidArgumentException("Unknown settings group [{$group}].");
        }

        return $groups[$group];
    }

    public function flush(): void
    {
        $this->stored = null;
        $this->cache->forget(config('settings.cache_key'));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function stored(): array
    {
        if ($this->stored !== null) {
            return $this->stored;
        }

        try {
            return $this->stored = $this->cache->rememberForever(
                config('settings.cache_key'),
                fn () => Setting::query()
                    ->get(['group', 'key', 'value'])
                    ->groupBy('group')
                    ->map(fn ($rows) => $rows->pluck('value', 'key')->all())
                    ->all(),
            );
        } catch (Throwable) {
            // The settings table may not exist yet (fresh install, early boot).
            return $this->stored = [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function field(string $group, string $key): array
    {
        $field = Arr::get($this->schema($group), $key);

        if ($field === null) {
            throw new InvalidArgumentException("Unknown setting [{$group}.{$key}].");
        }

        return $field;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $path): array
    {
        $parts = explode('.', $path, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException("Setting path [{$path}] must be in \"group.key\" form.");
        }

        return $parts;
    }

    private function decrypt(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
