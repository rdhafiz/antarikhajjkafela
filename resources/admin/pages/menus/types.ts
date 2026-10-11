import type { Translations } from '@admin/types';

export interface MenuItemNode {
    /** Stable client-side key (also for items not saved yet). */
    uid: string;
    id?: number;
    label: Translations;
    link_type: string;
    link_target: string | null;
    url: string | null;
    open_in_new_tab: boolean;
    is_visible: boolean;
    children: MenuItemNode[];
}

export interface LinkType {
    type: string;
    label: string;
    targets: { value: string; label: string }[] | null;
}

export interface MenuLocation {
    key: string;
    name: string;
    description: string;
    max_depth: number;
    items_count?: number;
}

let counter = 0;

export function uid(): string {
    counter += 1;
    return `n${Date.now().toString(36)}${counter}`;
}

/** Number of levels an item occupies including its descendants. */
export function subtreeHeight(item: MenuItemNode): number {
    return 1 + Math.max(0, ...item.children.map(subtreeHeight));
}

type ServerItem = Omit<MenuItemNode, 'uid' | 'children'> & { children: ServerItem[] };

export function fromServer(items: ServerItem[]): MenuItemNode[] {
    return items.map((item) => ({ ...item, uid: uid(), children: fromServer(item.children ?? []) }));
}

export function toServer(items: MenuItemNode[]): unknown[] {
    return items.map(({ uid: _uid, children, ...item }) => ({ ...item, children: toServer(children) }));
}
