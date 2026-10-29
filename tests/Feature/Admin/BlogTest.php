<?php

namespace Tests\Feature\Admin;

use App\Models\PageSection;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\SlugRedirect;
use Database\Seeders\BlogSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
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
            ->assertSeeText('Hajj 2028: what to prepare now, and how to pay safely')
            ->assertDontSeeText('What is Umrah?');

        $this->get('/blog?tag=Payment')
            ->assertOk()
            ->assertSeeText('Hajj 2028: what to prepare now, and how to pay safely')
            ->assertDontSeeText('How to perform Umrah');

        $this->get('/blog/how-to-perform-umrah')
            ->assertOk()
            ->assertSeeText('The steps of Umrah')
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSeeText('Related articles');
    }

    public function test_existing_sites_get_the_hajj_2028_posts_and_old_links_redirect(): void
    {
        // A site seeded before the refresh: the 2025 Umrah news and the old FAQ answer.
        $news = Post::where('slug', 'hajj-2028-registration')->firstOrFail();
        $news->update(['slug' => 'umrah-2025-november-group', 'title' => ['en' => 'Umrah 2025: November group registration open']]);
        Post::where('slug', 'what-is-umrah')->firstOrFail()->update(['title' => ['en' => 'What is Umrah?']]);
        $faq = PageSection::where('component', 'faq')->firstOrFail();
        $content = $faq->content;
        $content['items'][3]['answer']['en'] = '<p>Bring a copy of your NID or passport, an active mobile number and the government pre-registration fee. Our team completes the rest.</p>';
        $content['items'][0]['answer']['en'] = '<p>Edited by the admin.</p>';
        $faq->update(['content' => $content]);

        (require database_path('migrations/2026_10_20_100000_refresh_blog_posts_for_hajj_2028.php'))->up();

        $this->assertSame('Hajj 2028: registration open', $news->fresh()->getTranslation('title', 'en'));
        $this->assertSame('What is Umrah? Planning yours in 2026–27', Post::where('slug', 'what-is-umrah')->firstOrFail()->getTranslation('title', 'en'));
        $this->get('/blog/umrah-2025-november-group')->assertRedirect('/blog/hajj-2028-registration');
        $this->get('/blog/hajj-2028-registration')->assertOk()->assertSeeText('Antarik Hajj Kafela is now taking registrations for Hajj 2028');

        $items = $faq->fresh()->content['items'];
        $this->assertStringContainsString('Hajj 2028', $items[3]['answer']['en']);
        $this->assertSame('<p>Edited by the admin.</p>', $items[0]['answer']['en']);
    }

    public function test_long_posts_read_in_five_minutes_and_invite_hajj_and_umrah_readers_to_book(): void
    {
        $this->seed(TravelSeeder::class);

        foreach (['what-is-umrah' => '/umrah#packages', 'hajj-2028-registration' => '/hajj#packages'] as $slug => $target) {
            $response = $this->get("/blog/{$slug}")->assertOk()->assertSeeText('Free Booking Now')->assertSee(url($target), false);
            preg_match('/(\d+) min read/', strip_tags($response->getContent()), $match);
            $this->assertGreaterThanOrEqual(5, (int) ($match[1] ?? 0), "{$slug} reads in five minutes or more");
        }

        // A post without a Hajj or Umrah tag has no booking box.
        Post::where('slug', 'what-is-umrah')->first()->update(['tags' => ['Beginners']]);
        $this->get('/blog/what-is-umrah')->assertOk()->assertDontSee('post-cta', false);
    }

    public function test_posts_are_shown_in_bangla(): void
    {
        $this->withUnencryptedCookie('locale', 'bn')->get('/blog/what-is-umrah')
            ->assertOk()
            ->assertSeeText('উমরাহ কী?')
            ->assertSeeText('মিনিটে পড়ুন');
    }

    public function test_post_dates_use_bangla_digits_in_bangla(): void
    {
        $post = Post::where('slug', 'what-is-umrah')->firstOrFail();
        $post->update(['published_at' => '2025-08-10 09:00:00']);

        $this->get('/blog/what-is-umrah')->assertOk()->assertSeeText('10 August 2025');

        $this->withUnencryptedCookie('locale', 'bn')->get('/blog/what-is-umrah')
            ->assertOk()
            ->assertSeeText('১০ আগস্ট ২০২৫')
            ->assertDontSeeText('10 আগস্ট 2025');
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

    public function test_the_publish_date_cannot_be_moved_into_the_past(): void
    {
        $post = Post::where('slug', 'what-is-umrah')->firstOrFail();
        $post->update(['published_at' => '2025-08-10 09:00:00']);
        $admin = $this->superAdmin();

        // A past date is refused, a future one schedules the post.
        $this->asAdmin($admin)->putJson("/api/admin/posts/{$post->id}", $this->payload($post, ['published_at' => now()->subDay()->format('Y-m-d\TH:i')]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['published_at' => 'cannot be in the past']);

        // The date a post already has is kept, so older articles can still be edited.
        $this->asAdmin($admin)->putJson("/api/admin/posts/{$post->id}", $this->payload($post, ['slug' => 'umrah-basics']))->assertOk();
        $this->assertSame('2025-08-10 09:00', $post->fresh()->published_at->format('Y-m-d H:i'));

        $this->asAdmin($admin)->putJson("/api/admin/posts/{$post->id}", $this->payload($post->fresh(), ['published_at' => now()->addDay()->format('Y-m-d\TH:i')]))
            ->assertOk()
            ->assertJsonPath('data.state', 'scheduled');
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

        // Posts of a deleted category stay, shown without a category; it moves to the Trash and frees its address.
        $this->assertSame(2, Post::where('category_id', $news->id)->count());
        $this->assertNull(Post::where('category_id', $news->id)->first()->category);
        $this->assertSoftDeleted($news);
        $this->asAdmin($admin)->postJson('/api/admin/post-categories', ['name' => ['en' => 'News again'], 'slug' => 'news'])->assertCreated();
        $this->asAdmin($admin)->getJson('/api/admin/posts/meta')->assertOk()->assertJsonCount(3, 'categories');
    }

    public function test_sitemap_lists_live_posts(): void
    {
        Post::where('slug', 'what-is-umrah')->update(['status' => Post::STATUS_DRAFT]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(url('/blog/how-to-perform-umrah'), false)
            ->assertDontSee(url('/blog/what-is-umrah'), false);
    }

    public function test_posts_take_their_own_schema(): void
    {
        $post = Post::where('slug', 'what-is-umrah')->firstOrFail();
        $payload = $this->payload($post);
        $payload['seo']['schema'] = '{"@type": "HowTo", "name": "Perform Umrah"}';

        $this->asAdmin($this->superAdmin())->putJson("/api/admin/posts/{$post->id}", $payload)->assertOk();

        $this->get($post->fresh()->url())->assertOk()->assertSee('{"@type":"HowTo","name":"Perform Umrah"}', false)->assertSee('"@type":"BlogPosting"', false);
    }
}
