<script setup lang="ts">
import AppButton from '@admin/components/ui/AppButton.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import PageHeader from '@admin/components/ui/PageHeader.vue';
import { useForm } from '@admin/composables/useForm';
import { http } from '@admin/lib/http';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { CurrentUser } from '@admin/types';

const auth = useAuthStore();
const toast = useToastStore();

const profile = useForm({
    name: auth.user?.name ?? '',
    email: auth.user?.email ?? '',
    phone: auth.user?.phone ?? '',
});

const password = useForm({ current_password: '', password: '', password_confirmation: '' });

async function saveProfile(): Promise<void> {
    const response = await profile.submit(() => http.put<{ data: CurrentUser }>('/profile', profile.data));

    if (response) {
        auth.setUser(response.data.data);
        toast.success('Profile updated');
    }
}

async function savePassword(): Promise<void> {
    const response = await password.submit(() => http.put('/profile/password', password.data));

    if (response) {
        password.reset();
        toast.success('Password changed');
    }
}
</script>

<template>
    <div>
        <PageHeader title="My profile" description="Your account details and password." />

        <div class="grid gap-6 xl:grid-cols-2">
            <form class="card p-6" novalidate @submit.prevent="saveProfile">
                <h2 class="text-[15px] font-bold text-ink">Account details</h2>
                <div class="mt-5 space-y-4">
                    <AppInput v-model="profile.data.name" label="Full name" required :error="profile.error('name')" />
                    <AppInput v-model="profile.data.email" label="Email address" type="email" required :error="profile.error('email')" />
                    <AppInput v-model="profile.data.phone" label="Phone" type="tel" :error="profile.error('phone')" />
                </div>
                <div class="mt-6 flex justify-end">
                    <AppButton type="submit" :loading="profile.processing">Save changes</AppButton>
                </div>
            </form>

            <form class="card p-6" novalidate @submit.prevent="savePassword">
                <h2 class="text-[15px] font-bold text-ink">Change password</h2>
                <div class="mt-5 space-y-4">
                    <AppInput
                        v-model="password.data.current_password"
                        label="Current password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :error="password.error('current_password')"
                    />
                    <AppInput
                        v-model="password.data.password"
                        label="New password"
                        type="password"
                        autocomplete="new-password"
                        hint="At least 10 characters with letters and numbers."
                        required
                        :error="password.error('password')"
                    />
                    <AppInput
                        v-model="password.data.password_confirmation"
                        label="Confirm new password"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                </div>
                <div class="mt-6 flex justify-end">
                    <AppButton type="submit" :loading="password.processing">Update password</AppButton>
                </div>
            </form>
        </div>
    </div>
</template>
