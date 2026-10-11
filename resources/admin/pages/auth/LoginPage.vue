<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppCheckbox from '@admin/components/ui/AppCheckbox.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import { useForm } from '@admin/composables/useForm';
import { useAuthStore } from '@admin/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = useForm({ email: '', password: '', remember: true });

async function submit(): Promise<void> {
    const ok = await form.submit(() => auth.login(form.data).then(() => true));

    if (ok) {
        const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/admin')
            ? route.query.redirect
            : '/admin';
        router.replace(redirect);
    } else {
        form.data.password = '';
    }
}
</script>

<template>
    <div>
        <h1 class="text-[26px] font-bold tracking-[-0.02em] text-ink">Welcome back</h1>
        <p class="mt-1.5 text-sm text-ink-soft">Sign in to manage the website.</p>

        <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
            <AppInput
                v-model="form.data.email"
                label="Email address"
                type="email"
                autocomplete="username"
                required
                :error="form.error('email')"
            />
            <AppInput
                v-model="form.data.password"
                label="Password"
                type="password"
                autocomplete="current-password"
                required
                :error="form.error('password')"
            >
                <template #aside>
                    <RouterLink :to="{ name: 'forgot-password' }" class="text-[13px] font-semibold text-brand hover:underline">
                        Forgot password?
                    </RouterLink>
                </template>
            </AppInput>

            <AppCheckbox v-model="form.data.remember" label="Keep me signed in" />

            <AppButton type="submit" block :loading="form.processing" class="h-11">Sign in</AppButton>
        </form>
    </div>
</template>
