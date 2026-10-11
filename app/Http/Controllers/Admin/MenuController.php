<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Menus\MenuLinks;
use App\Domain\Menus\MenuTreeSaver;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $counts = Menu::withCount('items')->pluck('items_count', 'location');

        $locations = collect(config('menus.locations'))->map(fn ($location, $key) => [
            'key' => $key,
            'name' => $location['name'],
            'description' => $location['description'],
            'max_depth' => $location['max_depth'],
            'items_count' => $counts[$key] ?? 0,
        ])->values();

        return response()->json(['data' => $locations]);
    }

    public function show(string $location, MenuLinks $links): JsonResponse
    {
        $definition = $this->location($location);
        $menu = Menu::firstOrCreate(['location' => $location]);

        return response()->json([
            'data' => [
                'key' => $location,
                'name' => $definition['name'],
                'description' => $definition['description'],
                'max_depth' => $definition['max_depth'],
                'items' => $this->tree($menu->items()->get()->groupBy('parent_id'), null),
            ],
            'link_types' => $links->types(),
        ]);
    }

    public function update(Request $request, string $location, MenuLinks $links, MenuTreeSaver $saver, Locales $locales): JsonResponse
    {
        $definition = $this->location($location);
        $required = $locales->default();
        $language = $locales->available()[$required]['name'];

        $rules = MenuTreeSaver::rules($definition['max_depth'], $links, $required);
        $messages = [];

        foreach (array_keys($rules) as $key) {
            if (str_ends_with($key, ".label.{$required}")) {
                $messages["{$key}.required"] = "Every menu item needs a label in {$language}.";
            } elseif (str_ends_with($key, '.url')) {
                $messages["{$key}.regex"] = 'Links must start with http(s)://, /, mailto: or tel:.';
            }
        }

        $data = $request->validate($rules, $messages);

        $menu = Menu::firstOrCreate(['location' => $location]);
        $saver->save($menu, $data['items']);

        activity('menus')->causedBy($request->user())->event('updated')->log("Updated the {$definition['name']}");

        return $this->show($location, $links);
    }

    /**
     * @return array<string, mixed>
     */
    private function location(string $location): array
    {
        $definition = config("menus.locations.{$location}");

        abort_if($definition === null, 404);

        return $definition;
    }

    /**
     * @param  Collection<string|int, Collection<int, MenuItem>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function tree(Collection $byParent, ?int $parentId): array
    {
        return $byParent->get($parentId ?? '', collect())->map(fn (MenuItem $item) => [
            'id' => $item->id,
            'label' => $item->getTranslations('label'),
            'link_type' => $item->link_type,
            'link_target' => $item->link_target,
            'url' => $item->url,
            'open_in_new_tab' => $item->open_in_new_tab,
            'is_visible' => $item->is_visible,
            'children' => $this->tree($byParent, $item->id),
        ])->values()->all();
    }
}
