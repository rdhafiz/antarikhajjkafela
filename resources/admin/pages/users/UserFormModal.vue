<script setup lang="ts">
import { computed, watch } from 'vue';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppCheckbox from '@admin/components/ui/AppCheckbox.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import AppModal from '@admin/components/ui/AppModal.vue';
import AppSwitch from '@admin/components/ui/AppSwitch.vue';
import FormField from '@admin/components/ui/FormField.vue';
import { useForm } from '@admin/composables/useForm';
import { http } from '@admin/lib/http';
import { useAuthStore } from '@admin/stores/auth';
import type { AdminUser, Role } from '@admin/types';

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ user: AdminUser | null; roles: Role[] }>();
const emit = defineEmits<{ saved: [user: AdminUser] }>();

const auth = useAuthStore();

const blank = { name: '', email: '', phone: '', password: '', password_confirmation: '', is_active: true, roles: [] as string[] };
const form = useForm({ ...blank });

const editing = computed(() => props.user !== null);
const isSelf = computed(() => props.user?.id === auth.user?.id);

watch(open, (value) => {
    if (!value) return;

    form.reset(
        props.user
            ? { ...blank, name: props.user.name, email: props.user.email, phone: props.user.phone ?? '', is_active: props.user.is_active, roles: [...(props.user.roles ?? [])] }
            : blank,
    );
});

function toggleRole(name: string, checked: boolean | 'indeterminate'): void {
    form.data.roles = checked === true ? [...form.data.roles, name] : form.data.roles.filter((r) => r !== name);
}

async function save(): Promise<void> {
    const request = editing.value
        ? () => http.put<{ data: AdminUser }>(`/users/${props.user!.id}`, form.data)
        : () => http.post<{ data: AdminUser }>('/users', form.data);

    const response = await form.submit(request);

    if (response) {
        emit('saved', response.data.data);
        open.value = false;
    }
}
</script>

<template>
    <AppModal v-model:open="open" :title="editing ? 'Edit user' : 'Add user'" :persistent="form.processing">
        <form id="user-form" class="space-y-4" novalidate @submit.prevent="save">
            <AppInput v-model="form.data.name" label="Full name" required :error="form.error('name')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <AppInput v-model="form.data.email" label="Email address" type="email" autocomplete="off" required :error="form.error('email')" />
                <AppInput v-model="form.data.phone" label="Phone" type="tel" :error="form.error('phone')" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <AppInput
                    v-model="form.data.password"
                    :label="editing ? 'New password' : 'Password'"
                    type="password"
                    autocomplete="new-password"
                    :required="!editing"
                    :hint="editing ? 'Leave blank to keep the current password.' : 'At least 10 characters, letters and numbers.'"
                    :error="form.error('password')"
                />
                <AppInput v-model="form.data.password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
            </div>

            <FormField label="Roles" :error="form.error('roles') ?? form.errors['roles.0']?.[0]">
                <div class="grid gap-2.5 rounded-lg border border-line p-3.5 sm:grid-cols-2">
                    <AppCheckbox
                        v-for="role in roles"
                        :key="role.id"
                        :model-value="form.data.roles.includes(role.name)"
                        :label="role.label"
                        @update:model-value="toggleRole(role.name, $event)"
                    />
                </div>
            </FormField>

            <div class="rounded-lg border border-line p-3.5">
                <AppSwitch
                    v-model="form.data.is_active"
                    label="Active"
                    description="Inactive users cannot sign in."
                    :disabled="isSelf"
                />
            </div>
        </form>

        <template #footer>
            <AppButton variant="secondary" @click="open = false">Cancel</AppButton>
            <AppButton type="submit" form="user-form" :loading="form.processing">{{ editing ? 'Save changes' : 'Add user' }}</AppButton>
        </template>
    </AppModal>
</template>
