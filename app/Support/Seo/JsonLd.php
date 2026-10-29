<?php

namespace App\Support\Seo;

/**
 * Structured data typed by an admin (the site-wide setting and each page's own
 * schema): one object, a list of objects, or a full {"@context", "@graph"}
 * document as copied from a generator. Everything is flattened into nodes for
 * the page's @graph, without their own @context.
 */
class JsonLd
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function nodes(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $data = json_decode($json, true);

        return is_array($data) ? self::flatten($data) : [];
    }

    /**
     * Why the text can't be used as structured data, or null when it can.
     */
    public static function problem(string $json): ?string
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return __('This is not valid JSON (:error). Check for missing commas, quotes or brackets.', ['error' => json_last_error_msg()]);
        }

        if (! is_array($data) || $data === []) {
            return __('Enter a JSON object such as {"@type": "FAQPage", …}, or a list of them.');
        }

        foreach (self::flatten($data) as $node) {
            if (! isset($node['@type'])) {
                return __('Every item needs an "@type", for example "FAQPage" or "Event".');
            }
        }

        return self::flatten($data) === [] ? __('Enter a JSON object such as {"@type": "FAQPage", …}, or a list of them.') : null;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<array<string, mixed>>
     */
    private static function flatten(array $data): array
    {
        $items = array_is_list($data) ? $data : [$data];
        $nodes = [];

        foreach ($items as $item) {
            if (! is_array($item) || array_is_list($item)) {
                continue;
            }

            if (isset($item['@graph']) && is_array($item['@graph'])) {
                array_push($nodes, ...self::flatten($item['@graph']));

                continue;
            }

            unset($item['@context']);
            $nodes[] = $item;
        }

        return $nodes;
    }
}
