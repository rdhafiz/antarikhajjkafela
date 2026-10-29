<?php

namespace App\Rules;

use App\Support\Seo\JsonLd as JsonLdNodes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Admin-provided schema.org structured data: valid JSON made of objects that
 * each name an "@type" (a list, or a {"@context", "@graph"} document, is fine).
 */
class JsonLd implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if ($problem = JsonLdNodes::problem($value)) {
            $fail($problem);
        }
    }
}
