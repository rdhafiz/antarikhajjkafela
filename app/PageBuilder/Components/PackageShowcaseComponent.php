<?php

namespace App\PageBuilder\Components;

use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Service;
use App\PageBuilder\Component;
use App\PageBuilder\Field;
use Throwable;

class PackageShowcaseComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'package_showcase';
    }

    public function name(): string
    {
        return 'Package showcase';
    }

    public function description(): string
    {
        return 'Live package cards: featured packages, one service\'s packages or the newest ones.';
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function icon(): string
    {
        return 'layout-grid';
    }

    public function fields(): array
    {
        $services = [];

        try {
            $services = Service::orderBy('sort')->get()->mapWithKeys(fn (Service $s) => [(string) $s->id => $s->getTranslation('name', 'en')])->all();
        } catch (Throwable) {
            // Table not migrated yet.
        }

        return [
            ...$this->headingFields(),
            Field::select('source', 'Show', ['featured' => 'Featured packages', 'service' => 'Packages of one service', 'latest' => 'All packages, newest first']),
            Field::select('service', 'Service', $services)->default(null)->showIf('source', ['service']),
            Field::number('limit', 'How many', 1, 12)->default(3),
            Field::select('layout', 'Layout', ['grid' => 'Plain background', 'staggered' => 'Background pattern that moves while scrolling'])->default('grid'),
            Field::link('cta', 'Button below the cards'),
        ];
    }

    public function data(array $content, PageSection $section, Page $page): array
    {
        $packages = Package::published()
            ->with('banner', 'service')
            ->when(($content['source'] ?? 'featured') === 'featured', fn ($q) => $q->where('is_featured', true))
            ->when(($content['source'] ?? null) === 'service' && $content['service'], fn ($q) => $q->where('service_id', (int) $content['service']))
            ->orderByDesc('is_featured')
            ->orderBy('sort')
            ->latest('id')
            ->limit(max(1, min(12, (int) ($content['limit'] ?? 3))))
            ->get();

        return ['packages' => $packages];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'sand', 'spacing' => 'lg'];
    }
}
