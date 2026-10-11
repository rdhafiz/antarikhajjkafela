<?php

namespace App\Domain\Menus;

use App\Models\Menu;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a menu's items with a submitted tree: existing items are updated,
 * new ones created and anything missing from the tree deleted.
 */
class MenuTreeSaver
{
    /**
     * @param  list<array<string, mixed>>  $items  validated tree
     */
    public function save(Menu $menu, array $items): void
    {
        DB::transaction(function () use ($menu, $items) {
            $kept = [];
            $this->saveLevel($menu, $items, null, $kept);

            $menu->items()->whereNotIn('id', $kept)->delete();
        });

        app(MenuRepository::class)->flush($menu->location);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>  $kept
     */
    private function saveLevel(Menu $menu, array $items, ?int $parentId, array &$kept): void
    {
        foreach (array_values($items) as $position => $data) {
            $attributes = [
                'parent_id' => $parentId,
                'sort' => $position,
                'label' => array_filter($data['label'] ?? []),
                'link_type' => $data['link_type'],
                'link_target' => $data['link_type'] === MenuLinks::ROUTE ? $data['link_target'] : null,
                'url' => $data['link_type'] === MenuLinks::URL ? $data['url'] : null,
                'open_in_new_tab' => (bool) ($data['open_in_new_tab'] ?? false),
                'is_visible' => (bool) ($data['is_visible'] ?? true),
            ];

            // Only reuse ids that already belong to this menu.
            $item = isset($data['id'])
                ? $menu->items()->whereKey($data['id'])->first()
                : null;

            $item ? $item->update($attributes) : $item = $menu->items()->create($attributes);

            $kept[] = $item->id;

            $this->saveLevel($menu, $data['children'] ?? [], $item->id, $kept);
        }
    }

    /**
     * Validation rules for a tree up to $depth levels deep.
     *
     * @return array<string, mixed>
     */
    public static function rules(int $depth, MenuLinks $links, string $requiredLocale): array
    {
        $rules = ['items' => ['present', 'array', 'max:100']];
        $prefix = 'items.*';

        for ($level = 1; $level <= $depth; $level++) {
            $rules += [
                "{$prefix}.id" => ['nullable', 'integer'],
                "{$prefix}.label" => ['required', 'array'],
                "{$prefix}.label.{$requiredLocale}" => ['required', 'string', 'max:80'],
                "{$prefix}.label.*" => ['nullable', 'string', 'max:80'],
                "{$prefix}.link_type" => ['required', 'in:'.implode(',', $links->typeKeys())],
                "{$prefix}.link_target" => ['nullable', 'required_if:'.$prefix.'.link_type,'.MenuLinks::ROUTE, 'in:'.implode(',', $links->targetsFor(MenuLinks::ROUTE))],
                "{$prefix}.url" => ['nullable', 'required_if:'.$prefix.'.link_type,'.MenuLinks::URL, 'string', 'max:500', 'regex:#^(https?://|/|mailto:|tel:|\#)#i'],
                "{$prefix}.open_in_new_tab" => ['boolean'],
                "{$prefix}.is_visible" => ['boolean'],
                "{$prefix}.children" => $level < $depth ? ['array', 'max:50'] : ['prohibited'],
            ];

            $prefix .= '.children.*';
        }

        return $rules;
    }
}
