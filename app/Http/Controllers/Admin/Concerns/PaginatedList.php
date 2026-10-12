<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * One response shape for every paginated admin list: { data, meta, ...extra }.
 */
trait PaginatedList
{
    /**
     * @param  callable(mixed): mixed  $present
     * @param  array<string, mixed>  $extra
     */
    protected function listResponse(LengthAwarePaginator $page, callable $present, array $extra = []): JsonResponse
    {
        return response()->json([
            'data' => collect($page->items())->map($present)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            ...$extra,
        ]);
    }

    /**
     * Paginate an already loaded, already ordered collection.
     */
    protected function paginateCollection(Collection $items, int $perPage, int $page): LengthAwarePaginator
    {
        return new Paginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page);
    }

    /**
     * "Like" on a translatable JSON column or a plain column (Bangla is stored escaped in JSON).
     */
    protected function likeTerm(string $search): string
    {
        return '%'.addcslashes($search, '%_\\').'%';
    }
}
