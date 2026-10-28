<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A web address a page or service can take: not one the site already routes
 * (config page-builder.reserved_slugs) and not an old-site address that
 * redirects elsewhere (config/redirects.php), which nobody could reach.
 */
class FreeSlug implements ValidationRule
{
    /**
     * @param  list<string>  $except  reserved slugs this model may still use
     */
    public function __construct(private array $except = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (in_array($value, config('page-builder.reserved_slugs'), true) && ! in_array($value, $this->except, true)) {
            $fail('This address is used by the website. Choose another.');

            return;
        }

        $redirect = config("redirects.{$value}");
        if (is_string($redirect)) {
            $fail("/{$value} is an address from the old website that already redirects to {$redirect}, so this page could not be reached. Choose another.");
        }
    }
}
