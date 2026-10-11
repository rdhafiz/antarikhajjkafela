<?php

namespace App\PageBuilder\Components;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\PostCategory;
use App\PageBuilder\Component;
use App\PageBuilder\Field;
use Throwable;

class LatestPostsComponent extends Component
{
    use HasHeading;

    public function key(): string
    {
        return 'latest_posts';
    }

    public function name(): string
    {
        return 'Latest posts';
    }

    public function description(): string
    {
        return 'The newest blog and news posts, from all categories or one.';
    }

    public function category(): string
    {
        return 'dynamic';
    }

    public function icon(): string
    {
        return 'newspaper';
    }

    public function fields(): array
    {
        $categories = [];

        try {
            $categories = PostCategory::orderBy('sort')->get()->mapWithKeys(fn (PostCategory $c) => [(string) $c->id => $c->getTranslation('name', 'en')])->all();
        } catch (Throwable) {
            // Table not migrated yet.
        }

        return [
            ...$this->headingFields(),
            Field::select('category', 'Category', $categories)->default(null)->hint('Leave empty to show all categories.'),
            Field::number('limit', 'How many', 1, 9)->default(3),
            Field::link('cta', 'Button below the posts'),
        ];
    }

    public function data(array $content, PageSection $section, Page $page): array
    {
        $posts = Post::live()
            ->with('banner', 'category')
            ->when(filled($content['category'] ?? null), fn ($q) => $q->where('category_id', (int) $content['category']))
            ->latest('published_at')
            ->limit(max(1, min(9, (int) ($content['limit'] ?? 3))))
            ->get();

        return ['posts' => $posts];
    }

    public function defaultStyle(): array
    {
        return ['background' => 'white', 'spacing' => 'lg'];
    }
}
