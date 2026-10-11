<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\SlugRedirect;
use Database\Seeders\BlogSeeder;
use Database\Seeders\PageSeeder;
use Illuminate\Support\Facades\URL;

class BlogTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, BlogSeeder::class]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Post $post, array $overrides = []): array
    {
        $data = $this->asAdmin($this->superAdmin())->getJson("/api/admin/posts/{$post->id}")->json('data');

        return array_replace($data, $overrides);
    }

    public function test_blog_listing_and_post_pages_render(): void
    {
        $this->get('/blog')
            ->assertOk()
            ->assertSeeText('How to perform Umrah: a step-by-step guide')
            ->assertSeeText('News & briefings');

        $this->get('/blog?category=news')
            ->assertOk()
            ->assertSeeText('Important information for Hajj pilgrims')
            ->assertDontSeeText('What is Umrah?');

        $this->get('/blog?tag=Payment')
            ->assertOk()
            ->assertSeeText('Important information for Hajj pilgrims')
            ->assertDontSeeText('How to perform Umrah');

        $this->get('/blog/how-to-perform-umrah')
            ->assertOk()
            ->assertSeeText('The steps of Umrah')
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSeeText('Related articles');
    }

    public function test_posts_are_shown_in_bangla(): void
    {
        $this->withUnencryptedCookie('locale', 'bn')->get('/blog/what-is-umrah')
            ->assertOk()
            ->assertSeeText('উমরাহ কী?')
            ->assertSeeText('মিনিটে পড়ুন');
    }

    public function test_drafts_and_scheduled_posts_are_hidden_but_previewable(): void
    {
        $post = Post::where('slug', 'what-is-umrah')->firstOrFail();

        $post->update(['published_at' => now()->addDay()]);
        $this->assertSame('scheduled', $post->fresh()->state());
        $this->get('/blog/what-is-umrah')->assertNotFound();
        $this->get('/blog')->assertDontSeeText('What is Umrah?');

        $post->update(['status' => Post::STATUS_DRAFT, 'published_at' => null]);
        $this->get('/blog/what-is-umrah')->assertNotFound();

        $this->get(URL::temporarySignedRoute('posts.preview', now()->addHour(), $post))
            ->assertOk()
            ->assertSeeText('What is Umrah?')
            ->assertSee('noindex', false);

        $this->get(route('posts.preview', $post))->assertForbidden();
    }

    public function test_admin_creates_a_draft_and_publishes_it(): void
    {
        $admin = $this->adminWith(['blog.view', 'blog.create', 'blog.update']);
        $category = PostCategory::where('slug', 'guides')->first();

        $id = $this->asAdmin($admin)->postJson('/api/admin/posts', [
            'title' => ['en' => 'Packing list for Hajj', 'bn' => 'হজের প্যাকিং তালিকা'],
            'category_id' => $category->id,
        ])->assertCreated()->assertJsonPath('data.slug', 'packing-list-for-hajj')->assertJsonPath('data.state', 'draft')->json('data.id');

        $payload = $this->payload(Post::find($id), [
            'status' => 'published',
            'tags' => ['Hajj', ' hajj ', 'Packing'],
            'blocks' => [
                ['type' => 'text', 'body' => ['en' => '<p>Bring comfortable sandals.</p><script>alert(1)</script>']],
                ['type' => 'list', 'style' => 'check', 'items' => [['en' => 'Ihram'], ['en' => 'Unscented soap']]],
            ],
        ]);

        $this->asAdmin($admin)->putJson("/api/admin/posts/{$id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.state', 'published')
            ->assertJsonPath('data.tags', ['Hajj', 'Packing']);

        $post = Post::find($id);
        $this->assertNotNull($post->published_at);
        $this->assertStringNotContainsString('<script', json_encode($post->blocks));

        $this->get('/blog/packing-list-for-hajj')->assertOk()->assertSeeText('Bring comfortable sandals.')->assertSeeText('Unscented soap');
    }

    public function test_changing_the_slug_redirects_the_old_address(): void
    {
        $post = Post::where('slug', 'what-is-umrah')->firstOrFail();

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/posts/{$post->id}", $this->payload($post, ['slug' => 'umrah-explained']))
            ->assertOk();

        $this->assertSame($post->id, SlugRedirect::target('post', 'what-is-umrah'));
        $this->get('/blog/what-is-umrah')->assertRedirect('/blog/umrah-explained')->assertStatus(301);
    }

    public function test_validation_and_permissions(): void
    {
        $post = Post::firstOrFail();
        $other = Post::whereKeyNot($post->id)->firstOrFail();

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/posts/{$post->id}", $this->payload($post, [
            'slug' => $other->slug,
            'blocks' => [['type' => 'video', 'url' => 'https://example.com/video']],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['slug', 'blocks.0.url']);

        $payload = $this->payload($post);
        $viewer = $this->adminWith(['blog.view']);
        $this->asAdmin($viewer)->getJson('/api/admin/posts')->assertOk()->assertJsonCount(4, 'data');
        $this->asAdmin($viewer)->putJson("/api/admin/posts/{$post->id}", $payload)->assertForbidden();
        $this->asAdmin($viewer)->deleteJson("/api/admin/posts/{$post->id}")->assertForbidden();
        $this->asAdmin($this->adminWith(['reviews.view']))->getJson('/api/admin/posts')->assertForbidden();
    }

    public function test_categories_can_be_managed(): void
    {
        $admin = $this->adminWith(['blog.view', 'blog.update']);

        $this->asAdmin($admin)->postJson('/api/admin/post-categories', ['name' => ['en' => 'Health tips'], 'slug' => 'health-tips'])->assertCreated();
        $this->asAdmin($admin)->postJson('/api/admin/post-categories', ['name' => ['en' => 'Duplicate'], 'slug' => 'guides'])->assertJsonValidationErrors('slug');

        $news = PostCategory::where('slug', 'news')->first();
        $this->asAdmin($admin)->deleteJson("/api/admin/post-categories/{$news->id}")->assertNoContent();

        // Posts of a deleted category stay, without a category.
        $this->assertSame(2, Post::whereNull('category_id')->count());
        $this->asAdmin($admin)->getJson('/api/admin/posts/meta')->assertOk()->assertJsonCount(2, 'categories');
    }

    public function test_sitemap_lists_live_posts(): void
    {
        Post::where('slug', 'what-is-umrah')->update(['status' => Post::STATUS_DRAFT]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/blog/how-to-perform-umrah'), false)
            ->assertDontSee(url('/blog/what-is-umrah'), false);
    }
}
