<script setup lang="ts">
import { ref } from 'vue';
import { StickyNote, Trash2 } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import { useConfirm } from '@admin/composables/useConfirm';
import { errorMessage, http } from '@admin/lib/http';
import { formatDate } from '@admin/lib/format';
import { useToastStore } from '@admin/stores/toast';

export interface StaffNote {
    id: number;
    body: string;
    author: string | null;
    created_at: string | null;
    can_delete: boolean;
}

/**
 * Staff notes on a booking or an inquiry: each note is added on its own and
 * shows who wrote it and when (newest first), so nothing is overwritten.
 */
const props = defineProps<{ type: 'bookings' | 'inquiries'; id: number; canAdd: boolean }>();
const notes = defineModel<StaffNote[]>('notes', { default: () => [] });

const toast = useToastStore();
const { confirm } = useConfirm();
const body = ref('');
const saving = ref(false);
const error = ref<string | null>(null);

async function add(): Promise<void> {
    if (!body.value.trim() || saving.value) return;
    saving.value = true;
    error.value = null;
    try {
        const { data } = await http.post<{ data: StaffNote[] }>(`/${props.type}/${props.id}/notes`, { body: body.value });
        notes.value = data.data;
        body.value = '';
    } catch (e) {
        error.value = errorMessage(e);
    } finally {
        saving.value = false;
    }
}

async function remove(note: StaffNote): Promise<void> {
    const ok = await confirm({ title: 'Remove this note?', message: 'It goes to the Trash.', confirmLabel: 'Remove note' });
    if (!ok) return;
    try {
        const { data } = await http.delete<{ data: StaffNote[] }>(`/${props.type}/${props.id}/notes/${note.id}`);
        notes.value = data.data;
    } catch (e) {
        toast.error(errorMessage(e));
    }
}
</script>

<template>
    <div class="space-y-3">
        <form v-if="canAdd" class="space-y-2" @submit.prevent="add">
            <label :for="`note-${type}-${id}`" class="sr-only">New note</label>
            <textarea
                :id="`note-${type}-${id}`"
                v-model="body"
                rows="2"
                maxlength="5000"
                class="control resize-y"
                placeholder="Add a note, e.g. Called on Sunday, will visit the office"
                @keydown.ctrl.enter.prevent="add"
                @keydown.meta.enter.prevent="add"
            />
            <p v-if="error" class="text-[13px] font-medium text-red-600" role="alert">{{ error }}</p>
            <div class="flex items-center justify-between gap-3">
                <p class="text-[12px] text-ink-muted">Only staff see notes.</p>
                <AppButton type="submit" size="sm" variant="secondary" :loading="saving" :disabled="!body.trim()">Add note</AppButton>
            </div>
        </form>

        <p v-if="!notes.length" class="flex items-center gap-2 rounded-lg bg-canvas px-3 py-2.5 text-[13px] text-ink-muted">
            <StickyNote class="size-4 shrink-0" aria-hidden="true" />No notes yet.
        </p>
        <ol v-else class="space-y-2" aria-label="Notes, newest first">
            <li v-for="note in notes" :key="note.id" class="group rounded-lg border border-line bg-white px-3 py-2.5">
                <p class="text-[14px] leading-relaxed [overflow-wrap:anywhere] whitespace-pre-line text-ink">{{ note.body }}</p>
                <div class="mt-1.5 flex items-center justify-between gap-2 text-[12px] text-ink-muted">
                    <span>{{ note.author ?? 'Earlier note' }} · {{ formatDate(note.created_at, true) }}</span>
                    <button
                        v-if="note.can_delete"
                        type="button"
                        class="flex size-7 items-center justify-center rounded-md text-ink-muted hover:bg-red-50 hover:text-red-600"
                        :aria-label="`Remove note from ${formatDate(note.created_at, true)}`"
                        @click="remove(note)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
            </li>
        </ol>
    </div>
</template>
