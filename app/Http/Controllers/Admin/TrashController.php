<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Trash\TrashRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Everything deleted in the admin, newest first, with a restore for each.
 * There is deliberately no way to delete anything for good.
 */
class TrashController extends Controller
{
    /** Per kind; the Trash of a small agency never comes near it. */
    private const LIMIT = 500;

    public function __construct(private readonly TrashRegistry $trash) {}

    public function index(Request $request): JsonResponse
    {
        $types = $this->trash->types();
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(array_keys($types))],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', Rule::in([10, 20, 50, 100])],
        ]);

        $counts = [];
        $rows = collect();

        foreach ($types as $key => $type) {
            $items = $type['model']::onlyTrashed()->with([...($type['with'] ?? []), 'deletedBy'])->latest('deleted_at')->limit(self::LIMIT)->get();
            $counts[$key] = $items->count();

            if (($filters['type'] ?? null) && $filters['type'] !== $key) {
                continue;
            }

            $rows = $rows->concat($items->map(fn ($model) => [
                'type' => $key,
                'type_label' => $type['label'],
                'id' => $model->getKey(),
                'title' => (string) (($type['title'])($model) ?? '#'.$model->getKey()),
                'detail' => isset($type['detail']) ? ($type['detail'])($model) : null,
                'deleted_at' => $model->deleted_at?->toIso8601String(),
                'deleted_by' => $model->deletedBy?->name,
                'blocker' => $this->trash->blocker($key, $model),
            ]));
        }

        if ($search = trim($filters['search'] ?? '')) {
            $rows = $rows->filter(fn ($row) => str_contains(mb_strtolower($row['title'].' '.$row['detail']), mb_strtolower($search)));
        }

        $rows = $rows->sortByDesc('deleted_at')->values();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(), 'per_page' => $perPage, 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem()],
            'types' => collect($types)->map(fn ($type, $key) => ['key' => $key, 'label' => $type['label'], 'count' => $counts[$key]])->values(),
        ]);
    }

    public function restore(string $type, int $id): JsonResponse
    {
        $model = $this->trash->restore($type, $id);
        $link = $this->trash->type($type)['link'] ?? null;

        return response()->json(['data' => ['link' => $link ? '/admin/'.$link($model) : null]]);
    }
}
