<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Travel\PackageEditor;
use App\Domain\Travel\PackageSchema;
use App\Http\Controllers\Admin\Concerns\PaginatedList;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Service;
use App\PageBuilder\Field;
use App\Support\Icons;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    use PaginatedList;

    public function __construct(private readonly PackageEditor $editor) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'service_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([Package::STATUS_DRAFT, Package::STATUS_PUBLISHED])],
            'search' => ['nullable', 'string', 'max:100'],
            'featured' => ['nullable', Rule::in(['yes', 'no'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $packages = Package::query()
            ->with(['service', 'banner', 'prices'])
            ->withCount('types')
            ->when($filters['service_id'] ?? null, fn ($q, $id) => $q->where('service_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['featured'] ?? null, fn ($q, $featured) => $q->where('is_featured', $featured === 'yes'))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('slug', 'like', '%'.Str::slug($search).'%')
                ->orWhere('name', 'like', $this->likeTerm($search))))
            ->orderByDesc('is_featured')
            ->latest('updated_at')
            ->paginate($filters['per_page'] ?? 20);

        return $this->listResponse($packages, fn (Package $p) => [
            'id' => $p->id,
            'name' => (object) $p->getTranslations('name'),
            'slug' => $p->slug,
            'url' => $p->url(),
            'service' => $p->service ? (object) $p->service->getTranslations('name') : null,
            'status' => $p->status,
            'is_featured' => $p->is_featured,
            'booking_state' => $p->bookingState(),
            'booking_ends_on' => $p->booking_ends_on?->toDateString(),
            'duration_days' => $p->duration_days,
            'min_price' => $p->min_price !== null ? (float) $p->min_price : null,
            'types_count' => $p->types_count,
            'banner_id' => $p->banner_id,
            'banner_url' => $p->banner?->url('admin'),
            // What the package includes, for the list's quick-glance chips.
            'prices_count' => $p->prices->count(),
            'hotels_count' => $p->prices->flatMap(fn ($price) => $price->hotels ?? [])
                ->map(fn ($hotel) => json_encode([$hotel['heading'] ?? null, $hotel['subheading'] ?? null]))->unique()->count(),
            'flights_count' => count($p->flights ?? []),
            'sections_count' => count($p->sections ?? []),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'name' => ['required', 'array'],
            "name.{$default}" => ['required', 'string', 'max:160'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.*' => ['nullable', 'string', 'max:160'],
        ], ['name.en.required' => 'An English name is needed to create the web address.']);

        $package = $this->editor->save(new Package, [
            ...$data,
            'status' => Package::STATUS_DRAFT,
            'types' => [],
        ], $request->user());

        return response()->json(['data' => PackageEditor::present($package)], 201);
    }

    public function show(Package $package): JsonResponse
    {
        return response()->json(['data' => PackageEditor::present($package)]);
    }

    public function update(Request $request, Package $package): JsonResponse
    {
        $default = app(Locales::class)->default();

        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'name' => ['required', 'array'],
            "name.{$default}" => ['required', 'string', 'max:160'],
            'name.en' => ['required', 'string', 'max:160'],
            'name.*' => ['nullable', 'string', 'max:160'],
            'summary.*' => ['nullable', 'string', 'max:400'],
            'banner_id' => ['nullable', 'integer', 'exists:media,id'],
            'booking_starts_on' => ['nullable', 'date'],
            'booking_ends_on' => ['nullable', 'date', 'after_or_equal:booking_starts_on'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'status' => ['required', Rule::in([Package::STATUS_DRAFT, Package::STATUS_PUBLISHED])],
            'is_featured' => ['boolean'],
            'advance_type' => ['required', Rule::in(array_keys(Package::ADVANCE_TYPES))],
            'advance_value' => [
                'nullable', 'required_unless:advance_type,none', 'numeric', 'min:1',
                Rule::when($request->input('advance_type') === 'percentage', ['max:100']),
            ],
            'seo.title.*' => ['nullable', 'string', 'max:70'],
            'seo.description.*' => ['nullable', 'string', 'max:200'],
            'seo.image' => ['nullable', 'integer', 'exists:media,id'],
            'seo.noindex' => ['nullable', 'boolean'],
            ...$this->editor->nestedRules(),
        ], [
            'name.en.required' => 'An English name is needed for the web address.',
            'advance_value.required_unless' => 'Enter the advance amount.',
        ]);

        if ($data['status'] === Package::STATUS_PUBLISHED && empty($data['types'])) {
            return response()->json([
                'message' => 'Add at least one package type with a price before publishing.',
                'errors' => ['types' => ['Add at least one package type with a price before publishing.']],
            ], 422);
        }

        $package = $this->editor->save($package, $data, $request->user());

        return response()->json(['data' => PackageEditor::present($package)]);
    }

    public function duplicate(Request $request, Package $package): JsonResponse
    {
        return response()->json(['data' => PackageEditor::present($this->editor->duplicate($package, $request->user()))], 201);
    }

    public function destroy(Package $package): JsonResponse
    {
        $package->delete();

        return response()->json(status: 204);
    }

    /**
     * Field definitions and options for the package editor.
     */
    public function schema(): JsonResponse
    {
        $fields = fn (array $list) => array_map(fn (Field $f) => $f->toArray(), $list);

        return response()->json([
            'flights' => $fields(PackageSchema::flights()),
            'types' => $fields(PackageSchema::types()),
            'sections' => $fields(PackageSchema::sections()),
            'services' => Service::orderBy('sort')->get()->map(fn (Service $s) => ['id' => $s->id, 'name' => (object) $s->getTranslations('name'), 'is_active' => $s->is_active]),
            'advance_types' => Package::ADVANCE_TYPES,
            'icons' => Icons::contentIcons(),
        ]);
    }
}
