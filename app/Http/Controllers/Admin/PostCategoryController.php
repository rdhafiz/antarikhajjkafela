<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageSaveRequest;
use App\Models\PostCategory;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostCategoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $category = PostCategory::create($this->validated($request) + ['sort' => (int) PostCategory::max('sort') + 1]);

        return response()->json(['data' => ['id' => $category->id]], 201);
    }

    public function update(Request $request, PostCategory $category): JsonResponse
    {
        $category->update($this->validated($request, $category));

        return response()->json(['data' => ['id' => $category->id]]);
    }

    /**
     * Posts in a deleted category stay, without a category.
     */
    public function destroy(PostCategory $category): JsonResponse
    {
        $category->delete();

        return response()->json(status: 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:post_categories,id']])['ids'];

        foreach (array_values($ids) as $sort => $id) {
            PostCategory::whereKey($id)->update(['sort' => $sort]);
        }

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PostCategory $category = null): array
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'name' => ['required', 'array'],
            "name.{$default}" => ['required', 'string', 'max:60'],
            'name.*' => ['nullable', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:120', 'regex:'.PageSaveRequest::SLUG_PATTERN, Rule::unique('post_categories', 'slug')->ignore($category?->id)],
        ], ['slug.regex' => 'Use lowercase letters, numbers and single hyphens only.']);

        return ['name' => array_filter($data['name']), 'slug' => $data['slug']];
    }
}
