<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\TravelDates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageSaveRequest;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use App\Models\SlugRedirect;
use App\Rules\FreeSlug;
use App\Support\Icons;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = Service::query()
            ->with('image')
            // "Live" = published packages that visitors can see (the service must be shown too).
            ->withCount(['packages', 'packages as live_packages_count' => fn ($q) => $q->published()])
            ->orderBy('sort')
            ->get();

        return response()->json(['data' => $services->map(fn (Service $s) => $this->present($s))]);
    }

    public function store(Request $request): JsonResponse
    {
        $service = Service::create($this->validated($request) + ['sort' => (int) Service::max('sort') + 1]);

        return response()->json(['data' => $this->present($service->loadCount('packages'))], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $oldSlug = $service->slug;
        $service->update($this->validated($request, $service));

        // The old address keeps working: links and search results redirect to the new one.
        if ($oldSlug !== $service->slug) {
            SlugRedirect::remember('service', $oldSlug, $service->id);
        }
        Cache::forget('sitemap.xml');

        return response()->json(['data' => $this->present($service->loadCount('packages'))]);
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($service->packages()->exists()) {
            throw ValidationException::withMessages(['service' => 'Move or delete this service\'s packages first.']);
        }

        $service->delete();

        return response()->json(status: 204);
    }

    /**
     * Save the order shown on the website.
     */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:services,id,deleted_at,NULL']])['ids'];

        foreach (array_values($ids) as $sort => $id) {
            Service::whereKey($id)->update(['sort' => $sort]);
        }

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Service $service = null): array
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'name' => ['required', 'array'],
            "name.{$default}" => ['required', 'string', 'max:80'],
            // English always: the web address is built from the English name.
            'name.en' => ['required', 'string', 'max:80'],
            'name.*' => ['nullable', 'string', 'max:80'],
            'slug' => [
                'required', 'string', 'max:120', 'regex:'.PageSaveRequest::SLUG_PATTERN,
                Rule::unique('services', 'slug')->ignore($service?->id),
                new FreeSlug(except: ['hajj', 'umrah']),
            ],
            'summary.*' => ['nullable', 'string', 'max:300'],
            'headline.*' => ['nullable', 'string', 'max:90'],
            'highlight.*' => ['nullable', 'string', 'max:90'],
            'description.*' => ['nullable', 'string', 'max:3000'],
            'icon' => ['nullable', Rule::in(Icons::CONTENT)],
            'image_id' => ['nullable', 'integer', Rule::exists(Media::class, 'id')->whereNull('deleted_at')],
            'is_active' => ['boolean'],
            'travel_date' => ['nullable', Rule::in(array_keys(TravelDates::MODES))],
            'seo.title.*' => ['nullable', 'string', 'max:70'],
            'seo.description.*' => ['nullable', 'string', 'max:200'],
            'seo.image' => ['nullable', 'integer', Rule::exists(Media::class, 'id')->whereNull('deleted_at')],
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only.',
            'name.en.required' => 'An English name is needed: the website shows English first.',
        ]);

        if (Page::where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'A page already uses this address.']);
        }

        return [
            'name' => array_filter($data['name']),
            'slug' => $data['slug'],
            'summary' => array_filter($data['summary'] ?? []),
            'headline' => array_filter($data['headline'] ?? []),
            'highlight' => array_filter($data['highlight'] ?? []),
            'description' => array_filter($data['description'] ?? []),
            'icon' => $data['icon'] ?? null,
            'image_id' => $data['image_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'travel_date' => $data['travel_date'] ?? $service?->travel_date ?? 'none',
            'seo' => ['title' => array_filter($data['seo']['title'] ?? []), 'description' => array_filter($data['seo']['description'] ?? []), 'image' => $data['seo']['image'] ?? null],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => (object) $service->getTranslations('name'),
            'slug' => $service->slug,
            'url' => $service->url(),
            'summary' => (object) $service->getTranslations('summary'),
            'headline' => (object) $service->getTranslations('headline'),
            'highlight' => (object) $service->getTranslations('highlight'),
            'description' => (object) $service->getTranslations('description'),
            'icon' => $service->icon,
            'image_id' => $service->image_id,
            'is_active' => $service->is_active,
            'travel_date' => $service->travel_date ?? 'none',
            'seo' => ['title' => (object) ($service->seo['title'] ?? []), 'description' => (object) ($service->seo['description'] ?? []), 'image' => $service->seo['image'] ?? null],
            'packages_count' => $service->packages_count ?? 0,
            'live_packages_count' => $service->live_packages_count ?? 0,
            'image_url' => $service->relationLoaded('image') ? $service->image?->url('admin') : null,
        ];
    }
}
