<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Pages\PageEditor;
use App\Http\Controllers\Admin\Concerns\PaginatedList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageSaveRequest;
use App\Models\Media;
use App\Models\Page;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\SectionStyle;
use App\Support\Icons;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Default, custom and campaign pages. Campaigns use the "campaigns.*"
 * permissions; the other page types use "pages.*".
 */
class PageController extends Controller
{
    use PaginatedList;

    public function __construct(private readonly PageEditor $editor) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['required', Rule::in([Page::TYPE_DEFAULT, Page::TYPE_CUSTOM, Page::TYPE_CAMPAIGN])],
            'status' => ['nullable', Rule::in([Page::STATUS_DRAFT, Page::STATUS_PUBLISHED])],
            // Campaigns only: draft, scheduled, live or expired.
            'state' => ['nullable', Rule::in(['draft', 'scheduled', 'live', 'expired'])],
            'sort' => ['nullable', Rule::in(['updated', 'title'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = $filters['type'];
        $this->authorizeType($type, 'view');

        $pages = Page::query()
            ->where('type', $type)
            ->withCount('sections')
            ->with('sections:id,page_id,sort,content')
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('slug', 'like', $this->likeTerm($search))
                ->orWhere('title', 'like', $this->likeTerm($search))))
            ->latest('updated_at')
            ->get();

        // Counts for the filter drawer, before the status filters are applied.
        $counts = [
            'statuses' => $pages->countBy('status'),
            'states' => $type === Page::TYPE_CAMPAIGN ? $pages->countBy(fn (Page $page) => $page->campaignState()) : (object) [],
            'types' => Page::query()->toBase()->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type')->map(fn ($n) => (int) $n),
        ];

        $pages = $pages
            ->when($filters['status'] ?? null, fn ($pages, $status) => $pages->where('status', $status))
            ->when($type === Page::TYPE_CAMPAIGN ? ($filters['state'] ?? null) : null, fn ($pages, $state) => $pages->filter(fn (Page $page) => $page->campaignState() === $state))
            ->values();

        $sort = $filters['sort'] ?? null;
        if ($sort === 'title') {
            // Alphabetical in the language the list shows (Collator orders Bangla correctly).
            $locale = app(Locales::class)->default();
            $collator = class_exists(\Collator::class) ? new \Collator($locale) : null;
            $pages = $pages->sort(fn (Page $a, Page $b) => $collator
                ? $collator->compare($a->getTranslation('title', $locale), $b->getTranslation('title', $locale))
                : strcmp(mb_strtolower($a->getTranslation('title', $locale)), mb_strtolower($b->getTranslation('title', $locale))))->values();
        } elseif ($type === Page::TYPE_DEFAULT && $sort !== 'updated') {
            // Built-in pages keep the order of the site menu.
            $order = array_flip(array_keys(config('page-builder.default_pages')));
            $pages = $pages->sortBy(fn (Page $page) => $order[$page->system_key] ?? 99)->values();
        }

        $page = $this->paginateCollection($pages, (int) ($filters['per_page'] ?? 20), (int) ($filters['page'] ?? 1));
        $covers = $this->covers($page->getCollection());

        return $this->listResponse($page, fn (Page $page) => [...$this->summary($page), 'cover_url' => $covers[$page->id] ?? null], ['counts' => $counts]);
    }

    /**
     * A picture for each page in the list: the top section's image, else the share image.
     *
     * @param  Collection<int, Page>  $pages
     * @return array<int, string|null>
     */
    private function covers($pages): array
    {
        $ids = $pages->mapWithKeys(function (Page $page) {
            $content = $page->sections->first()?->content ?? [];
            $id = $content['image'] ?? $content['background_image'] ?? $page->seo['image'] ?? null;

            return [$page->id => is_numeric($id) ? (int) $id : null];
        });
        $media = Media::whereIn('id', $ids->filter()->unique()->values())->get()->keyBy('id');

        return $ids->map(fn ($id) => $id ? $media->get($id)?->url('admin') : null)->all();
    }

    public function store(Request $request): JsonResponse
    {
        $locale = app(Locales::class)->default();

        $data = $request->validate([
            'type' => ['required', Rule::in([Page::TYPE_CUSTOM, Page::TYPE_CAMPAIGN])],
            'title' => ['required', 'array'],
            "title.{$locale}" => ['required', 'string', 'max:160'],
            'title.*' => ['nullable', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'regex:'.PageSaveRequest::SLUG_PATTERN, Rule::notIn(config('page-builder.reserved_slugs')), 'unique:pages,slug', 'unique:services,slug'],
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
