<script setup lang="ts">
import { ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import { GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { pickText } from '@admin/pages/content/builder/types';
import { useConfirm } from '@admin/composables/useConfirm';
import { useForm } from '@admin/composables/useForm';
import { errorMessage, http } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import { useToastStore } from '@admin/stores/toast';
import type { Translations } from '@admin/types';
import type { PostCategory } from './types';

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ categories: PostCategory[] }>();
const emit = defineEmits<{ changed: [] }>();

const app = useAppStore();
const toast = useToastStore();
const { confirm } = useConfirm();

const list = ref<PostCategory[]>([]);
const editing = ref<PostCategory | 'new' | null>(null);
const form = useForm({ name: {} as Translations, slug: '' });
let slugTouched = false;

watch(() => [open.value, props.categories] as const, () => {
    list.value = [...props.categories];
}, { immediate: true });

function slugify(value: string): string {
    return value.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
}

watch(() => form.data.name.en, (name) => {
    if (!slugTouched) form.data.slug = slugify(name ?? '');
});

function edit(category: PostCategory | 'new'): void {
    editing.value = category;
    slugTouched = category !== 'new';
    form.reset(category === 'new' ? { name: {}, slug: '' } : { name: { ...category.name }, slug: category.slug });
}

async function save(): Promise<void> {
    const target = editing.value;
    const response = await form.submit(() =>
        target && target !== 'new' ? http.put(`/post-categories/${target.id}`, form.data) : http.post('/post-categories', form.data),
    );
    if (response) {
        toast.success(target === 'new' ? 'Category added' : 'Category updated');
        editing.value = null;
        emit('changed');
    }
}

async function remove(category: PostCategory): Promise<void> {
    const message = category.posts_count
        ? `Its ${category.posts_count} post(s) stay on the website without a category.`
        : 'It goes to the Trash, where a super admin can restore it.';
    if (!(await confirm({ title: `Delete "${pickText(category.name)}"?`, message, confirmLabel: 'Delete' }))) return;
    try {
        await http.delete(`/post-categories/${category.id}`);
        toast.success('Category deleted');
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}

async function saveOrder(): Promise<void> {
    try {
        await http.put('/post-categories/reorder', { ids: list.value.map((c) => c.id) });
        emit('changed');
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <AppModal v-model:open="open" title="Categories" description="Shown as filters on the blog page, in this order." size="lg">
        <form v-if="editing" id="category-form" class="mb-5 space-y-4 rounded-xl border border-line bg-canvas p-4" novalidate @submit.prevent="save">
            <div class="grid gap-4 sm:grid-cols-2">
                <TranslatableInput v-model="form.data.name" label="Name" required :errors="form.errors" error-key="name" />
                <FormField label="Filter address" for="category-slug" :error="form.error('slug')" required hint="Used as /blog?category=…">
                    <input id="category-slug" v-model="form.data.slug" class="control" @input="slugTouched = true" @blur="form.data.slug = slugify(form.data.slug)" />
                </FormField>
            </div>
            <div class="flex justify-end gap-2">
                <AppButton variant="secondary" size="sm" @click="editing = null">Cancel</AppButton>
                <AppButton type="submit" size="sm" :loading="form.processing">{{ editing === 'new' ? 'Add category' : 'Save' }}</AppButton>
            </div>
        </form>

        <p v-if="!list.length && !editing" class="py-6 text-center text-sm text-ink-muted">No categories yet.</p>
        <VueDraggable v-model="list" handle=".drag" :animation="160" class="space-y-2" @end="saveOrder">
            <div v-for="category in list" :key="category.id" class="flex items-center gap-2 rounded-lg border border-line bg-white p-2.5">
                <button type="button" class="drag flex size-8 cursor-grab items-center justify-center rounded-lg text-ink-muted hover:bg-canvas" aria-label="Drag to reorder"><GripVertical class="size-4" /></button>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink">{{ pickText(category.name, app.defaultLocale) }}</p>
                    <p class="text-[12px] text-ink-muted">{{ category.slug }} · {{ category.posts_count }} posts</p>
                </div>
                <button type="button" class="rounded-lg p-2 text-ink-muted hover:bg-canvas hover:text-brand" :aria-label="`Edit ${pickText(category.name)}`" @click="edit(category)"><Pencil class="size-4" /></button>
                <button type="button" class="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600" :aria-label="`Delete ${pickText(category.name)}`" @click="remove(category)"><Trash2 class="size-4" /></button>
            </div>
        </VueDraggable>

        <template #footer>
            <AppButton v-if="!editing" variant="secondary" :icon="Plus" @click="edit('new')">Add category</AppButton>
            <AppButton @click="open = false">Done</AppButton>
        </template>
    </AppModal>
</template>
