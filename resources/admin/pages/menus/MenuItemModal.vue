<script setup lang="ts">
import { computed, reactive, ref, useId, watch } from 'vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import FormField from '@admin/components/ui/FormField.vue';
import TranslatableInput from '@admin/components/ui/TranslatableInput.vue';
import { useAppStore } from '@admin/stores/app';
import type { LinkType, MenuItemNode } from './types';

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ item: MenuItemNode | null; linkTypes: LinkType[]; isNew: boolean }>();
const emit = defineEmits<{ save: [item: MenuItemNode] }>();

const id = useId();
const app = useAppStore();
const draft = reactive<Omit<MenuItemNode, 'children'>>({
    uid: '',
    label: {},
    link_type: 'route',
    link_target: null,
    url: null,
    open_in_new_tab: false,
    is_visible: true,
});
const errors = ref<Record<string, string[]>>({});

watch(open, (value) => {
    if (!value || !props.item) return;
    const { children: _children, ...rest } = props.item;
    Object.assign(draft, structuredClone({ ...rest, label: { ...rest.label } }));
    errors.value = {};
});

const currentType = computed(() => props.linkTypes.find((t) => t.type === draft.link_type) ?? null);

// Prefill the label from the chosen page when it is still empty.
watch(
    () => draft.link_target,
    (target) => {
        if (!target || Object.values(draft.label).some(Boolean)) return;
        const option = currentType.value?.targets?.find((t) => t.value === target);
        if (option) draft.label = { ...draft.label, en: option.label };
    },
);

function validate(): boolean {
    const result: Record<string, string[]> = {};

    const required = app.defaultLocale;
    if (!draft.label[required]?.trim()) {
        const language = app.locales.find((l) => l.code === required)?.name ?? required;
        result[`label.${required}`] = [`A label in ${language} is required.`];
    }
    if (currentType.value?.targets && !draft.link_target) result.link_target = ['Choose where this item links to.'];
    if (draft.link_type === 'url' && !/^(https?:\/\/|\/|mailto:|tel:|#)/i.test(draft.url ?? '')) {
        result.url = ['Enter a link starting with https://, /, mailto: or tel:.'];
    }

    errors.value = result;
    return Object.keys(result).length === 0;
}

function save(): void {
    if (!validate() || !props.item) return;

    emit('save', {
        ...props.item,
        ...structuredClone({ ...draft, label: { ...draft.label } }),
        link_target: currentType.value?.targets ? draft.link_target : null,
        url: draft.link_type === 'url' ? draft.url : null,
        children: props.item.children,
    });
    open.value = false;
}
</script>

<template>
    <AppModal v-model:open="open" :title="isNew ? 'Add menu item' : 'Edit menu item'">
        <form :id="id" class="space-y-5" novalidate @submit.prevent="save">
            <FormField label="Link to" :for="`${id}-type`">
                <div class="grid gap-2 sm:grid-cols-3">
                    <label
                        v-for="type in linkTypes"
                        :key="type.type"
                        class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-[13px] font-semibold transition-colors"
                        :class="draft.link_type === type.type ? 'border-brand bg-brand-soft text-brand' : 'border-line text-ink-soft hover:border-[#cbd3df]'"
                    >
                        <input v-model="draft.link_type" type="radio" :value="type.type" class="sr-only" />
                        {{ type.label }}
                    </label>
                </div>
            </FormField>

            <FormField v-if="currentType?.targets" :label="currentType.label" :for="`${id}-target`" :error="errors.link_target?.[0]">
                <select :id="`${id}-target`" v-model="draft.link_target" class="control" :class="{ 'control-invalid': errors.link_target }">
                    <option :value="null" disabled>Choose…</option>
                    <option v-for="target in currentType.targets" :key="target.value" :value="target.value">{{ target.label }}</option>
                </select>
            </FormField>

            <AppInput
                v-if="draft.link_type === 'url'"
                v-model="draft.url"
                label="URL"
                placeholder="https://… or /page"
                hint="A full address, a path on this website such as /hajj, or mailto: / tel:."
                :error="errors.url?.[0]"
            />

            <TranslatableInput v-model="draft.label" label="Label" error-key="label" :errors="errors" required />

            <div class="space-y-3 rounded-lg border border-line p-3.5">
                <AppSwitch v-if="draft.link_type !== 'none'" v-model="draft.open_in_new_tab" label="Open in a new tab" />
                <AppSwitch v-model="draft.is_visible" label="Visible on the website" description="Hidden items stay here so you can show them again later." />
            </div>
        </form>

        <template #footer>
            <AppButton variant="secondary" @click="open = false">Cancel</AppButton>
            <AppButton type="submit" :form="id">{{ isNew ? 'Add item' : 'Apply' }}</AppButton>
        </template>
    </AppModal>
</template>
