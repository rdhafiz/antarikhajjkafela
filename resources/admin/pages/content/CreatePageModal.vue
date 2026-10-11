<script setup lang="ts">
import { computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { useForm } from '@admin/composables/useForm';
import { http } from '@admin/lib/http';
import { useAppStore } from '@admin/stores/app';
import type { PageSummary } from './builder/types';

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ type: 'custom' | 'campaign' }>();

const router = useRouter();
const app = useAppStore();
const form = useForm({ title: {} as Record<string, string | null>, slug: '' });
let slugTouched = false;

const host = computed(() => app.siteUrl.replace(/^https?:\/\//, '').replace(/\/$/, ''));

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^\w\s-]/g, '')
        .trim()
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 80);
}

watch(open, (value) => {
    if (value) {
        form.reset({ title: {}, slug: '' });
        slugTouched = false;
    }
});

watch(
    () => form.data.title.en,
    (title) => {
        if (!slugTouched) form.data.slug = slugify(title ?? '');
    },
);

async function create(): Promise<void> {
    const response = await form.submit(() => http.post<{ data: PageSummary }>('/pages', { type: props.type, ...form.data }));

    if (response) {
        open.value = false;
        router.push({ name: 'page-editor', params: { id: response.data.data.id } });
    }
}
</script>

<template>
    <AppModal v-model:open="open" :title="type === 'campaign' ? 'New campaign page' : 'New page'" :description="type === 'campaign' ? 'Starts with a hero, lead form, FAQ and call to action that you can change.' : 'Starts with a banner and a text section that you can change.'" :persistent="form.processing">
        <form id="create-page" class="space-y-4" novalidate @submit.prevent="create">
            <TranslatableInput v-model="form.data.title" label="Title" required :errors="form.errors" error-key="title" :placeholder="type === 'campaign' ? 'Hajj 2027 registration' : 'Refund policy'" />
            <FormField label="Web address" for="new-page-slug" :error="form.error('slug')" required>
                <div class="flex items-stretch overflow-hidden rounded-lg border bg-white focus-within:border-brand focus-within:ring-3 focus-within:ring-brand/12" :class="form.error('slug') ? 'border-red-400' : 'border-line'">
                    <span class="flex items-center border-r border-line bg-canvas px-3 text-[13px] text-ink-muted">{{ host }}/</span>
                    <input id="new-page-slug" v-model="form.data.slug" class="min-w-0 flex-1 px-3 py-2 text-sm outline-none" spellcheck="false" @input="slugTouched = true" @blur="form.data.slug = slugify(form.data.slug)" />
                </div>
            </FormField>
            <p class="text-[13px] text-ink-muted">New pages are saved as drafts. Publish them from the editor when they are ready.</p>
        </form>
        <template #footer>
            <AppButton variant="secondary" @click="open = false">Cancel</AppButton>
            <AppButton type="submit" form="create-page" :loading="form.processing">Create and edit</AppButton>
        </template>
    </AppModal>
</template>
