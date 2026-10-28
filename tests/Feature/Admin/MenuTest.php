<?php

namespace Tests\Feature\Admin;

use App\Domain\Menus\MenuRepository;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\URL;

class MenuTest extends AdminTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function item(string $en, array $extra = []): array
    {
        return ['label' => ['en' => $en], 'link_type' => 'route', 'link_target' => 'home', ...$extra];
    }

    public function test_locations_and_link_types_are_listed(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->getJson('/api/admin/menus')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'primary')
            ->assertJsonPath('data.1.max_depth', 1);

        $this->asAdmin($admin)->getJson('/api/admin/menus/primary')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('link_types.0.type', 'route');

        $this->asAdmin($admin)->getJson('/api/admin/menus/sidebar')->assertNotFound();
    }

    public function test_a_nested_tree_is_saved_in_order(): void
    {
        $this->asAdmin($this->superAdmin())->putJson('/api/admin/menus/primary', ['items' => [
            $this->item('Home'),
            $this->item('Packages', ['link_type' => 'none', 'link_target' => null, 'children' => [
                $this->item('Hajj', ['link_target' => 'hajj', 'children' => [
                    $this->item('VIP', ['link_type' => 'url', 'link_target' => null, 'url' => '/hajj/vip']),
                ]]),
                $this->item('Umrah', ['link_target' => 'umrah', 'label' => ['en' => 'Umrah', 'bn' => 'উমরাহ']]),
            ]]),
        ]])->assertOk()->assertJsonPath('data.items.1.children.1.label.bn', 'উমরাহ');

        $menu = Menu::where('location', 'primary')->first();
        $this->assertSame(5, $menu->items()->count());
        $this->assertSame(['Hajj', 'Umrah'], MenuItem::where('parent_id', $menu->rootItems()->skip(1)->first()->id)->orderBy('sort')->get()->map->getTranslation('label', 'en')->all());
    }

    public function test_items_missing_from_the_tree_are_deleted_and_ids_are_reused(): void
    {
        $admin = $this->superAdmin();
        $response = $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [$this->item('A'), $this->item('B')]]);
        [$a] = $response->json('data.items');

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [['id' => $a['id'], ...$this->item('A renamed')]]])
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $a['id']);
    }

    public function test_depth_limits_and_link_rules_are_enforced(): void
    {
        $admin = $this->superAdmin();

        // The footer allows a single level.
        $this->asAdmin($admin)->putJson('/api/admin/menus/footer', ['items' => [
            $this->item('Parent', ['children' => [$this->item('Child')]]),
        ]])->assertJsonValidationErrors('items.0.children');

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [
            $this->item('Bad', ['link_type' => 'url', 'link_target' => null, 'url' => 'javascript:alert(1)']),
        ]])->assertJsonValidationErrors('items.0.url');

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [
            $this->item('Nowhere', ['link_target' => 'made-up']),
        ]])->assertJsonValidationErrors('items.0.link_target');

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [
            ['label' => ['bn' => 'শুধু বাংলা'], 'link_type' => 'none'],
        ]])->assertJsonValidationErrors('items.0.label.en');
    }

    public function test_labels_are_required_in_the_default_language(): void
    {
        settings()->set('localization', ['default_locale' => 'bn']);

        $this->asAdmin($this->superAdmin())->putJson('/api/admin/menus/primary', ['items' => [$this->item('English only')]])
            ->assertJsonValidationErrors(['items.0.label.bn' => 'Every menu item needs a label in Bangla.']);
    }

    public function test_ids_from_another_menu_are_not_reused(): void
    {
        $admin = $this->superAdmin();
        $footerItem = $this->asAdmin($admin)->putJson('/api/admin/menus/footer', ['items' => [$this->item('Footer')]])->json('data.items.0.id');

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [['id' => $footerItem, ...$this->item('Primary')]]])->assertOk();

        $this->assertSame('Footer', MenuItem::find($footerItem)->getTranslation('label', 'en'));
    }

    public function test_public_tree_shows_visible_items_in_the_current_language(): void
    {
        $this->seed(MenuSeeder::class);
        $repository = app(MenuRepository::class);

        app()->setLocale('bn');
        $tree = $repository->tree('primary');

        $this->assertSame('হোম', $tree[0]['label']);
        $this->assertSame(url('/hajj'), $tree[1]['url']);
        // "About" is a plain link; "Reviews" is seeded hidden until there are real reviews.
        $this->assertSame([], collect($tree)->firstWhere('label', 'আমাদের সম্পর্কে')['children']);
        $this->assertNull(collect($tree)->firstWhere('label', 'রিভিউ'));

        // Hidden items disappear once the menu is saved (cache is flushed).
        $this->asAdmin($this->superAdmin())->putJson('/api/admin/menus/primary', ['items' => [
            $this->item('Home'),
            $this->item('Secret', ['is_visible' => false]),
        ]])->assertOk();

        app()->setLocale('en');
        $this->assertSame(['Home'], array_column($repository->tree('primary'), 'label'));
    }

    public function test_links_to_pages_that_are_not_live_stay_out_of_the_menu(): void
    {
        $admin = $this->superAdmin();
        $draft = Page::create(['type' => Page::TYPE_CUSTOM, 'title' => ['en' => 'Refunds'], 'slug' => 'refunds', 'status' => Page::STATUS_DRAFT]);
        $campaign = Page::create(['type' => Page::TYPE_CAMPAIGN, 'title' => ['en' => 'Eid offer'], 'slug' => 'eid-offer', 'status' => Page::STATUS_PUBLISHED, 'starts_at' => now()->addDay()]);

        // #124: campaign pages can be picked; #123: the builder says which targets aren't on the website yet.
        $pages = collect($this->asAdmin($admin)->getJson('/api/admin/menus/primary')->json('link_types'))->firstWhere('type', 'page')['targets'];
        $this->assertSame('Draft', collect($pages)->firstWhere('value', (string) $draft->id)['offline']);
        $this->assertSame(['value' => (string) $campaign->id, 'label' => 'Eid offer · campaign (Scheduled)', 'offline' => 'Scheduled'], collect($pages)->firstWhere('value', (string) $campaign->id));

        $this->asAdmin($admin)->putJson('/api/admin/menus/primary', ['items' => [
            $this->item('Home'),
            ['label' => ['en' => 'Refunds'], 'link_type' => 'page', 'link_target' => (string) $draft->id],
            ['label' => ['en' => 'Eid'], 'link_type' => 'page', 'link_target' => (string) $campaign->id],
            ['label' => ['en' => 'More'], 'link_type' => 'page', 'link_target' => (string) $draft->id, 'children' => [$this->item('Sub')]],
        ]])->assertOk();

        // Not live: left out of the menu (no dead "#" link); with sub-items it stays as a heading.
        $tree = app(MenuRepository::class)->tree('primary');
        $this->assertSame(['Home', 'More'], array_column($tree, 'label'));
        $this->assertNull($tree[1]['url']);

        // Live again: back in the menu (pages flush the menus when saved; campaigns also expire from the cache).
        $draft->update(['status' => Page::STATUS_PUBLISHED]);
        $campaign->update(['starts_at' => now()->subHour()]);
        $this->assertSame(['Home', 'Refunds', 'Eid', 'More'], array_column(app(MenuRepository::class)->tree('primary'), 'label'));
    }

    public function test_cached_links_follow_the_current_domain(): void
    {
        $this->seed(MenuSeeder::class);
        $repository = app(MenuRepository::class);

        // Built under one host (e.g. a CLI run with the local APP_URL)...
        URL::forceRootUrl('http://antarikhajjkafela.test');
        $repository->tree('primary');

        // ...and served on the live domain from the same cache.
        URL::forceRootUrl('http://example.com');
        $tree = $repository->tree('primary');

        $this->assertSame('http://example.com', $tree[0]['url']);
        $this->assertSame('http://example.com/hajj', $tree[1]['url']);

        URL::forceRootUrl(null);
    }

    public function test_menu_permissions_are_enforced(): void
    {
        $viewer = $this->adminWith(['menus.view']);

        $this->asAdmin($viewer)->getJson('/api/admin/menus/primary')->assertOk();
        $this->asAdmin($viewer)->putJson('/api/admin/menus/primary', ['items' => []])->assertForbidden();
    }
}
