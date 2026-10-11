<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Pages\PageEditor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageSaveRequest;
use App\Models\Page;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\SectionStyle;
use App\Support\Icons;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Default, custom and campaign pages. Campaigns use the "campaigns.*"
 * permissions; the other page types use "pages.*".
 */
class PageController extends Controller
{
    public function __construct(private readonly PageEditor $editor) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in([Page::TYPE_DEFAULT, Page::TYPE_CUSTOM, Page::TYPE_CAMPAIGN])]])['type'];
        $this->authorizeType($type, 'view');

        $pages = Page::query()
            ->where('type', $type)
            ->withCount('sections')
            ->latest('updated_at')
            ->get();

        if ($type === Page::TYPE_DEFAULT) {
            $order = array_flip(array_keys(config('page-builder.default_pages')));
            $pages = $pages->sortBy(fn (Page $page) => $order[$page->system_key] ?? 99)->values();
        }

        return response()->json(['data' => $pages->map(fn (Page $page) => $this->summary($page))]);
    }

    public function store(Request $request): JsonResponse
    {
        $locale = app(Locales::class)->default();

        $data = $request->validate([
            'type' => ['required', Rule::in([Page::TYPE_CUSTOM, Page::TYPE_CAMPAIGN])],
            'title' => ['required', 'array'],
            "title.{$locale}" => ['required', 'string', 'max:160'],
            'title.*' => ['nullable', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:'.PageSaveRequest::SLUG_PATTERN, Rule::notIn(config('page-builder.reserved_slugs')), 'unique:pages,slug'],
        ], ['slug.regex' => 'Use lowercase letters, numbers and single hyphens only.', 'slug.not_in' => 'This address is used by the website. Choose another.']);

        $this->authorizeType($data['type'], 'create');

        $page = $this->editor->create($data, $request->user());

        return response()->json(['data' => $this->summary($page)], 201);
    }

    public function show(Page $page): JsonResponse
    {
        $this->authorizeType($page->type, 'view');

        return response()->json(['data' => $this->detail($page)]);
    }

    public function update(PageSaveRequest $request, Page $page): JsonResponse
    {
        $this->authorizeType($page->type, 'update');

        $page = $this->editor->save($page, $request->validated(), $request->user());

        return response()->json(['data' => $this->detail($page)]);
    }

    public function duplicate(Request $request, Page $page): JsonResponse
    {
        abort_if($page->type === Page::TYPE_DEFAULT, 422, 'Default pages cannot be duplicated.');
        $this->authorizeType($page->type, 'create');

        return response()->json(['data' => $this->summary($this->editor->duplicate($page, $request->user()))], 201);
    }

    public function destroy(Page $page): JsonResponse
    {
        abort_if($page->type === Page::TYPE_DEFAULT, 422, 'Default pages cannot be deleted.');
        $this->authorizeType($page->type, 'delete');

        $page->delete();

        return response()->json(status: 204);
    }

    /**
     * A temporary signed link to view the page as saved, even if it is a draft.
     */
    public function previewUrl(Page $page): JsonResponse
    {
        $this->authorizeType($page->type, 'view');

        return response()->json([
            'url' => URL::temporarySignedRoute('pages.preview', now()->addHour(), ['page' => $page->id]),
        ]);
    }

    /**
     * Component catalogue and editor metadata for the page builder.
     */
    public function builder(Request $request, ComponentRegistry $registry): JsonResponse
    {
        $type = $request->string('type', Page::TYPE_CUSTOM)->value();

        return response()->json([
            'components' => array_map(fn ($c) => $c->toArray(), array_values($registry->all())),
            'pickable' => array_map(fn ($c) => $c->key(), $registry->pickable($type)),
            'style_fields' => array_map(fn ($f) => $f->toArray(), SectionStyle::fields()),
            'icons' => Icons::contentIcons(),
            'reserved_slugs' => config('page-builder.reserved_slugs'),
        ]);
    }

    private function authorizeType(string $type, string $action): void
    {
        Gate::authorize(($type === Page::TYPE_CAMPAIGN ? 'campaigns' : 'pages').".{$action}");
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Page $page): array
    {
        return [
            'id' => $page->id,
            'type' => $page->type,
            'system_key' => $page->system_key,
            'title' => $page->getTranslations('title'),
            'slug' => $page->slug,
            'url' => $page->url(),
            'status' => $page->status,
            'state' => $page->isCampaign() ? $page->campaignState() : $page->status,
            'starts_at' => $page->starts_at?->toIso8601String(),
            'expires_at' => $page->expires_at?->toIso8601String(),
            'sections_count' => $page->sections_count ?? $page->sections()->count(),
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Page $page): array
    {
        return [
            ...$this->summary($page),
            'has_listing' => (bool) config("page-builder.default_pages.{$page->system_key}.listing", false),
            'seo' => [
                'title' => (object) ($page->seo['title'] ?? []),
                'description' => (object) ($page->seo['description'] ?? []),
                'image' => $page->seo['image'] ?? null,
                'noindex' => (bool) ($page->seo['noindex'] ?? false),
            ],
            'focused_layout' => $page->focused_layout,
            'tracking' => (object) ($page->tracking ?? []),
            'sections' => $page->sections->map(fn ($section) => [
                'id' => $section->id,
                'component' => $section->component,
                'is_visible' => $section->is_visible,
                'content' => (object) ($section->content ?? []),
                'style' => (object) ($section->style ?? []),
            ])->values(),
        ];
    }
}
