<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import { useForm } from '@admin/composables/useForm';
import { http } from '@admin/lib/http';
import { useToastStore } from '@admin/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const form = useForm({
    token: String(route.query.token ?? ''),
    email: String(route.query.email ?? ''),
    password: '',
    password_confirmation: '',
});

async function submit(): Promise<void> {
    const response = await form.submit(() => http.post('/auth/reset-password', form.data));

    if (response) {
        toast.success('Password updated', 'You can now sign in with your new password.');
        router.replace({ name: 'login' });
    }
}
</script>

<template>
    <div>
        <h1 class="text-[26px] font-bold tracking-[-0.02em] text-ink">Choose a new password</h1>
        <p class="mt-1.5 text-sm text-ink-soft">Use at least 10 characters with letters and numbers.</p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
            <AppInput v-model="form.data.email" label="Email address" type="email" autocomplete="username" required :error="form.error('email')" />
            <AppInput v-model="form.data.password" label="New password" type="password" autocomplete="new-password" required :error="form.error('password')" />
            <AppInput v-model="form.data.password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
            <AppButton type="submit" block :loading="form.processing" class="h-11">Reset password</AppButton>
        </form>
    </div>
</template>
