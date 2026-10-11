<?php

namespace App\Http\Controllers\Site;

use App\Domain\Blog\PostPresenter;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\SlugRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class BlogController extends Controller
{
    public function __construct(private readonly PostPresenter $presenter) {}

    public function show(string $slug): Response|RedirectResponse
    {
        $post = Post::live()->where('slug', $slug)->first();

        if (! $post) {
            $target = SlugRedirect::target('post', $slug);
            $moved = $target ? Post::live()->find($target) : null;

            abort_unless($moved, 404);

            return redirect()->to($moved->url(), 301);
        }

        return $this->render($post);
    }

    /**
     * Signed preview link from the admin: shows drafts and scheduled posts.
     */
    public function preview(Post $post): Response
    {
        return $this->render($post, preview: true);
    }

    private function render(Post $post, bool $preview = false): Response
    {
        $post->loadMissing('banner', 'category', 'authorImage');
        $locale = app()->getLocale();
        $data = $this->presenter->detail($post);
        $blog = Page::where('system_key', 'blog')->first();
        $blogUrl = $blog?->url() ?? url('blog');

        $seo = seo()->title($post->seo['title'][$locale] ?? $data['title'])
            ->description($post->seo['description'][$locale] ?? $data['excerpt'])
            ->canonical($post->url())
            ->type('article')
            ->noindex($preview || (bool) ($post->seo['noindex'] ?? false))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb($blog?->getTranslation('title', $locale) ?? __('Blog'), $blogUrl);

        if ($post->category) {
            $seo->breadcrumb($post->category->getTranslation('name', $locale), $post->category->url());
        }

        $seo->breadcrumb($data['title'], $post->url())->schema($this->presenter->schema($post, $data));

        $shareImage = ! empty($post->seo['image']) ? Media::find($post->seo['image']) : null;
        $seo->image($shareImage?->url('og') ?? $post->banner?->url('lg'));

        return response()->view('site.blog.show', [
            'post' => $post,
            'p' => $data,
            'related' => $this->presenter->related($post),
            'blogUrl' => $blogUrl,
            'preview' => $preview,
        ]);
    }
}
