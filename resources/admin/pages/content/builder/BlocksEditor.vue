<script setup lang="ts">
import { computed, ref } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { GripVertical, Image as ImageIcon, List, Minus, Plus, Rows3, Table2, Trash2, Type, Video } from 'lucide-vue-next';
import RichTextEditor from '@admin/components/ui/RichTextEditor.vue';
import MediaField from '@admin/components/media/MediaField.vue';
import { useAppStore } from '@admin/stores/app';
import { uid } from './types';

/**
 * Editor for content blocks (text, table, list, image, video). One language is
 * edited at a time for all blocks, which keeps long content manageable.
 */
type Tr = Record<string, string | null>;
interface Block {
    _id: string;
    type: 'text' | 'table' | 'list' | 'image' | 'video';
    body?: Tr;
    header?: boolean;
    rows?: Tr[][];
    caption?: Tr;
    style?: 'bullet' | 'check' | 'number';
    items?: Tr[];
    image?: number | null;
    url?: string | null;
}

const blocks = defineModel<Block[]>({ default: () => [] });
const props = defineProps<{ label: string; errors: Record<string, string[]>; path: string; max?: number }>();

const app = useAppStore();
const locale = ref(app.defaultLocale);
const max = computed(() => props.max ?? 40);

const list = computed({
    get: () => {
        for (const block of blocks.value ?? []) if (!block._id) block._id = uid();
        return blocks.value ?? [];
    },
    set: (value) => (blocks.value = value),
});

const types = [
    { type: 'text', label: 'Text', icon: Type },
    { type: 'list', label: 'List', icon: List },
    { type: 'table', label: 'Table', icon: Table2 },
    { type: 'image', label: 'Image', icon: ImageIcon },
    { type: 'video', label: 'Video', icon: Video },
] as const;

function create(type: Block['type']): void {
    const base = { _id: uid(), type };
    const block: Block = {
        text: { ...base, body: {} },
        list: { ...base, style: 'check' as const, items: [{}, {}] },
        table: { ...base, header: true, rows: [[{}, {}], [{}, {}]], caption: {} },
        image: { ...base, image: null, caption: {} },
        video: { ...base, url: null, caption: {} },
    }[type];
    list.value = [...list.value, block];
}

function remove(index: number): void {
    list.value = list.value.filter((_, i) => i !== index);
}

function setText(target: Tr | undefined, value: string): Tr {
    return { ...(target ?? {}), [locale.value]: value || null };
}

function error(index: number, key: string): string | undefined {
    const prefix = `${props.path}.${index}.${key}`;
    const found = Object.entries(props.errors).find(([k]) => k === prefix || k.startsWith(`${prefix}.`));
    return found?.[1]?.[0];
}

// Table helpers
function addRow(block: Block): void {
    const columns = block.rows?.[0]?.length ?? 2;
    block.rows = [...(block.rows ?? []), Array.from({ length: columns }, () => ({}))];
}
function addColumn(block: Block): void {
    if ((block.rows?.[0]?.length ?? 0) >= 8) return;
    block.rows = (block.rows ?? [[]]).map((row) => [...row, {}]);
}
function removeRow(block: Block, index: number): void {
    if ((block.rows?.length ?? 0) <= 1) return;
    block.rows = block.rows!.filter((_, i) => i !== index);
}
function removeColumn(block: Block, index: number): void {
    if ((block.rows?.[0]?.length ?? 0) <= 1) return;
    block.rows = block.rows!.map((row) => row.filter((_, i) => i !== index));
}
</script>

<template>
    <div>
        <div class="mb-2.5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13px] font-semibold text-ink">{{ label }} <span class="font-medium text-ink-muted">({{ list.length }})</span></p>
            <div class="flex items-center gap-2">
                <span class="text-[12px] text-ink-muted">Editing</span>
                <div class="flex rounded-md bg-canvas p-0.5" role="tablist" aria-label="Content language">
                    <button
                        v-for="item in app.locales"
                        :key="item.code"
                        type="button"
                        role="tab"
                        :aria-selected="locale === item.code"
                        class="rounded px-2.5 py-0.5 text-[11px] font-bold uppercase"
                        :class="locale === item.code ? 'bg-white text-brand shadow-sm' : 'text-ink-muted hover:text-ink'"
                        @click="locale = item.code"
                    >
                        {{ item.native }}
                    </button>
                </div>
            </div>
        </div>

        <VueDraggable v-model="list" handle=".block-handle" :animation="160" class="space-y-2.5">
            <div v-for="(block, index) in list" :key="block._id" class="rounded-xl border border-line bg-white">
                <div class="flex items-center gap-2 border-b border-line px-2 py-1.5">
                    <button type="button" class="block-handle flex size-7 cursor-grab items-center justify-center rounded-md text-ink-muted hover:bg-canvas active:cursor-grabbing" aria-label="Drag to reorder">
                        <GripVertical class="size-4" />
                    </button>
                    <component :is="types.find((t) => t.type === block.type)?.icon" class="size-4 text-brand" />
                    <span class="flex-1 text-[12px] font-bold tracking-wide text-ink-soft uppercase">{{ types.find((t) => t.type === block.type)?.label }}</span>
                    <button type="button" class="rounded-md p-1.5 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label="Remove block" @click="remove(index)">
                        <Trash2 class="size-3.5" />
                    </button>
                </div>

                <div class="p-3" :lang="locale">
                    <!-- Text -->
                    <RichTextEditor
                        v-if="block.type === 'text'"
                        :key="`${block._id}-${locale}`"
                        :model-value="block.body?.[locale] ?? null"
                        :invalid="!!error(index, 'body')"
                        min-height="110px"
                        @update:model-value="block.body = { ...(block.body ?? {}), [locale]: $event }"
                    />

                    <!-- List -->
                    <div v-else-if="block.type === 'list'" class="space-y-2">
                        <div class="flex gap-1 rounded-lg bg-canvas p-0.5 text-[12px] font-semibold">
                            <button v-for="style in (['check', 'bullet', 'number'] as const)" :key="style" type="button" class="flex-1 rounded-md py-1 capitalize" :class="block.style === style ? 'bg-white text-brand shadow-sm' : 'text-ink-muted'" @click="block.style = style">
                                {{ style === 'check' ? 'Checkmarks' : style === 'bullet' ? 'Bullets' : 'Numbers' }}
                            </button>
                        </div>
                        <div v-for="(item, i) in block.items" :key="i" class="flex gap-2">
                            <input :value="item[locale] ?? ''" class="control py-1.5" placeholder="List item" @input="block.items![i] = setText(item, ($event.target as HTMLInputElement).value)" />
                            <button type="button" class="rounded-md px-2 text-ink-muted hover:text-red-600" aria-label="Remove item" @click="block.items = block.items!.filter((_, k) => k !== i)">
                                <Minus class="size-4" />
                            </button>
                        </div>
                        <button type="button" class="flex items-center gap-1 text-[12px] font-semibold text-brand hover:underline" @click="block.items = [...(block.items ?? []), {}]">
                            <Plus class="size-3.5" /> Add item
                        </button>
                    </div>

                    <!-- Table -->
                    <div v-else-if="block.type === 'table'" class="space-y-2">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-[13px]">
                                <tbody>
                                    <tr v-for="(row, r) in block.rows" :key="r">
                                        <td v-for="(cell, c) in row" :key="c" class="border border-line p-0">
                                            <input
                                                :value="cell[locale] ?? ''"
                                                class="w-full min-w-24 bg-transparent px-2 py-1.5 outline-none focus:bg-brand-soft/50"
                                                :class="{ 'font-bold': r === 0 && block.header }"
                                                @input="row[c] = setText(cell, ($event.target as HTMLInputElement).value)"
                                            />
                                        </td>
                                        <td class="w-7 pl-1">
                                            <button type="button" class="text-ink-muted hover:text-red-600" aria-label="Remove row" @click="removeRow(block, r)"><Minus class="size-3.5" /></button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td v-for="(_, c) in block.rows?.[0]" :key="c" class="pt-1 text-center">
                                            <button type="button" class="text-ink-muted hover:text-red-600" aria-label="Remove column" @click="removeColumn(block, c)"><Minus class="mx-auto size-3.5" /></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-[12px] font-semibold">
                            <button type="button" class="flex items-center gap-1 text-brand hover:underline" @click="addRow(block)"><Rows3 class="size-3.5" /> Add row</button>
                            <button type="button" class="flex items-center gap-1 text-brand hover:underline" @click="addColumn(block)"><Plus class="size-3.5" /> Add column</button>
                            <label class="ml-auto inline-flex items-center gap-1.5 text-ink-soft"><input v-model="block.header" type="checkbox" class="accent-[var(--admin-primary)]" /> First row is a header</label>
                        </div>
                        <input :value="block.caption?.[locale] ?? ''" class="control py-1.5" placeholder="Caption (optional)" @input="block.caption = setText(block.caption, ($event.target as HTMLInputElement).value)" />
                    </div>

                    <!-- Image -->
                    <div v-else-if="block.type === 'image'" class="space-y-2">
                        <MediaField v-model="block.image" label="Image" usage="content" aspect="16 / 10" :error="error(index, 'image')" />
                        <input :value="block.caption?.[locale] ?? ''" class="control py-1.5" placeholder="Caption (optional)" @input="block.caption = setText(block.caption, ($event.target as HTMLInputElement).value)" />
                    </div>

                    <!-- Video -->
                    <div v-else-if="block.type === 'video'" class="space-y-2">
                        <input v-model="block.url" type="url" class="control py-1.5" :class="{ 'control-invalid': error(index, 'url') }" placeholder="https://www.youtube.com/watch?v=…" />
                        <p v-if="error(index, 'url')" class="text-[12px] font-medium text-red-600">Use a YouTube link.</p>
                        <input :value="block.caption?.[locale] ?? ''" class="control py-1.5" placeholder="Caption (optional)" @input="block.caption = setText(block.caption, ($event.target as HTMLInputElement).value)" />
                    </div>
                </div>
            </div>
        </VueDraggable>

        <div v-if="list.length < max" class="mt-2.5 flex flex-wrap gap-2">
            <button
                v-for="item in types"
                :key="item.type"
                type="button"
                class="flex items-center gap-1.5 rounded-lg border border-dashed border-[#cfd7e2] px-3 py-2 text-[12px] font-semibold text-ink-soft transition-colors hover:border-brand/50 hover:text-brand"
                @click="create(item.type)"
            >
                <Plus class="size-3.5" /> <component :is="item.icon" class="size-3.5" /> {{ item.label }}
            </button>
        </div>
    </div>
</template>
