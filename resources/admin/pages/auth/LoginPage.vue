<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowRight, Eye, EyeOff, Lock, Mail, ShieldCheck } from 'lucide-vue-next';
import AppCheckbox from '@admin/components/ui/AppCheckbox.vue';
import { useForm } from '@admin/composables/useForm';
import { useAuthStore } from '@admin/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

// Off by default: the office shares machines, and a long-lived session
// should be something staff choose rather than something they inherit.
const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);
const capsLock = ref(false);

function checkCaps(event: KeyboardEvent): void {
    capsLock.value = event.getModifierState?.('CapsLock') ?? false;
}

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
        <div class="ahk-rise" style="--d: 0.75s">
            <p class="flex items-center gap-2.5 text-[12px] font-bold tracking-[0.18em] text-brand uppercase">
                <span class="h-0.5 w-6 rounded-full bg-gold" aria-hidden="true" />
                Admin portal
            </p>
            <h1 class="mt-4 text-[30px] leading-[1.15] font-bold tracking-[-0.025em] text-ink">Welcome back</h1>
            <p class="mt-2 text-[15px] text-ink-soft">Sign in to manage the website, packages and bookings.</p>
        </div>

        <form class="mt-9 space-y-5" novalidate @submit.prevent="submit">
            <!-- Email -->
            <div class="ahk-rise" style="--d: 0.85s">
                <label for="login-email" class="mb-2 block text-[13px] font-semibold text-ink">Email address</label>
                <div class="ahk-field" :class="{ 'is-invalid': form.error('email') }">
                    <Mail class="ahk-field-icon" aria-hidden="true" />
                    <input
                        id="login-email"
                        v-model="form.data.email"
                        type="email"
                        autocomplete="username"
                        inputmode="email"
                        required
                        autofocus
                        placeholder="name@example.com"
                        :aria-invalid="form.error('email') ? 'true' : undefined"
                        :aria-describedby="form.error('email') ? 'login-email-error' : undefined"
                    />
                </div>
                <p v-if="form.error('email')" id="login-email-error" class="mt-2 text-[13px] font-medium text-red-600" role="alert">{{ form.error('email') }}</p>
            </div>

            <!-- Password -->
            <div class="ahk-rise" style="--d: 0.95s">
                <label for="login-password" class="mb-2 block text-[13px] font-semibold text-ink">Password</label>
                <div class="ahk-field" :class="{ 'is-invalid': form.error('password') }">
                    <Lock class="ahk-field-icon" aria-hidden="true" />
                    <input
                        id="login-password"
                        v-model="form.data.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        placeholder="Your password"
                        :aria-invalid="form.error('password') ? 'true' : undefined"
                        :aria-describedby="[form.error('password') ? 'login-password-error' : '', capsLock ? 'login-caps' : ''].filter(Boolean).join(' ') || undefined"
                        @keyup="checkCaps"
                        @keydown="checkCaps"
                    />
                    <button
                        type="button"
                        class="ahk-reveal-btn"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        :aria-pressed="showPassword"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="size-[18px]" />
                        <Eye v-else class="size-[18px]" />
                    </button>
                </div>
                <p v-if="capsLock" id="login-caps" class="mt-2 text-[13px] font-medium text-amber-700">Caps Lock is on.</p>
                <p v-if="form.error('password')" id="login-password-error" class="mt-2 text-[13px] font-medium text-red-600" role="alert">{{ form.error('password') }}</p>
            </div>

            <div class="ahk-rise pt-1" style="--d: 1.05s">
                <AppCheckbox v-model="form.data.remember" label="Keep me signed in" />
            </div>

            <div class="ahk-rise pt-2" style="--d: 1.15s">
                <button type="submit" class="ahk-submit group" :disabled="form.processing">
                    <svg v-if="form.processing" class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    <span>{{ form.processing ? 'Signing in…' : 'Sign in' }}</span>
                    <ArrowRight v-if="!form.processing" class="size-[18px] transition-[translate] duration-500 group-hover:translate-x-1" aria-hidden="true" />
                </button>
            </div>
        </form>

        <div class="ahk-rise mt-8 flex flex-col items-center justify-between gap-3 border-t border-line pt-6 text-[13px] sm:flex-row" style="--d: 1.25s">
            <p class="flex items-center gap-2 text-ink-soft">
                <ShieldCheck class="size-4 text-accent" aria-hidden="true" />
                Authorised staff only
            </p>
            <a href="/" class="font-semibold text-brand hover:underline">Back to website</a>
        </div>
    </div>
</template>
