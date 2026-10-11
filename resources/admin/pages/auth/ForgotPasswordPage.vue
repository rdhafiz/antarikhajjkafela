<script setup lang="ts">
import { ref } from 'vue';
import { ArrowLeft, MailCheck } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import { useForm } from '@admin/composables/useForm';
import { http } from '@admin/lib/http';

const form = useForm({ email: '' });
const sent = ref(false);

async function submit(): Promise<void> {
    const response = await form.submit(() => http.post('/auth/forgot-password', form.data));
    if (response) sent.value = true;
}
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'login' }" class="mb-8 inline-flex items-center gap-1.5 text-[13px] font-semibold text-ink-soft hover:text-ink">
            <ArrowLeft class="size-4" /> Back to sign in
        </RouterLink>

        <template v-if="sent">
            <div class="mb-5 flex size-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                <MailCheck class="size-6" />
            </div>
            <h1 class="text-[26px] font-bold tracking-[-0.02em] text-ink">Check your email</h1>
            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                If <strong class="text-ink">{{ form.data.email }}</strong> belongs to an admin account, we've sent a link to reset the password.
                The link expires in 60 minutes.
            </p>
        </template>

        <template v-else>
            <h1 class="text-[26px] font-bold tracking-[-0.02em] text-ink">Forgot your password?</h1>
            <p class="mt-1.5 text-sm text-ink-soft">Enter your email and we'll send you a reset link.</p>

            <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
                <AppInput v-model="form.data.email" label="Email address" type="email" autocomplete="username" required :error="form.error('email')" />
                <AppButton type="submit" block :loading="form.processing" class="h-11">Send reset link</AppButton>
            </form>
        </template>
    </div>
</template>
