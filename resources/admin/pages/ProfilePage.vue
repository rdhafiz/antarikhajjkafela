<script setup lang="ts">
import { computed, ref } from 'vue';
import { Check, Circle, Clock, Crown, Eye, EyeOff, KeyRound, LockKeyhole, ShieldCheck, Undo2, UserRound } from 'lucide-vue-next';
import AppButton from '@admin/components/ui/AppButton.vue';
import AppInput from '@admin/components/ui/AppInput.vue';
import FormField from '@admin/components/ui/FormField.vue';
import ListHero from '@admin/components/ui/ListHero.vue';
import PhoneInput from '@admin/components/ui/PhoneInput.vue';
import headerPhoto from '@admin/assets/profile-header.webp';
import { navigation } from '@admin/navigation';
import { useForm } from '@admin/composables/useForm';
import { http } from '@admin/lib/http';
import { formatDate, initials, titleCase } from '@admin/lib/format';
import { useAuthStore } from '@admin/stores/auth';
import { useToastStore } from '@admin/stores/toast';
import type { CurrentUser } from '@admin/types';

const auth = useAuthStore();
const toast = useToastStore();

const original = () => ({ name: auth.user?.name ?? '', email: auth.user?.email ?? '', phone: (auth.user?.phone ?? null) as string | null });
const profile = useForm(original());
const profileDirty = computed(() => JSON.stringify(profile.data) !== JSON.stringify(original()));

const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const showPasswords = ref(false);

/** The same rules as the server: at least 10 characters with letters and numbers. */
const rules = computed(() => {
    const value = password.data.password;
    return [
        { label: 'At least 10 characters', ok: value.length >= 10 },
        { label: 'Letters', ok: /\p{L}/u.test(value) },
        { label: 'Numbers', ok: /\d/.test(value) },
        { label: 'Both new passwords match', ok: value !== '' && value === password.data.password_confirmation },
    ];
});
const strength = computed(() => rules.value.filter((r) => r.ok).length);
const canChangePassword = computed(() => password.data.current_password !== '' && strength.value === rules.value.length);

const roleLabel = (name: string) => titleCase(name.replace(/-/g, ' '));
const lastSignIn = computed(() => (auth.user?.last_login_at ? formatDate(auth.user.last_login_at, true) : 'This is your first sign-in'));

/** What this account can open, using each module's sidebar colour and icon. */
const access = computed(() => {
    const permissions = auth.user?.permissions ?? [];
    const everything = permissions.includes('*');
    return navigation
        .flatMap((section) => section.items)
        .map((item) => {
            const module = item.permission?.split('.')[0] ?? '';
            const actions = everything ? ['all'] : permissions.filter((p) => p.startsWith(`${module}.`)).map((p) => p.split('.')[1]);
            return { ...item, actions, canEdit: everything || actions.some((a) => a !== 'view') };
        })
        .filter((item) => !item.permission || item.actions.length);
});

async function saveProfile(): Promise<void> {
    const response = await profile.submit(() => http.put<{ data: CurrentUser }>('/profile', profile.data));

    if (response) {
        auth.setUser(response.data.data);
        profile.reset(original());
        toast.success('Profile updated');
    }
}

async function savePassword(): Promise<void> {
    const response = await password.submit(() => http.put('/profile/password', password.data));

    if (response) {
        password.reset();
        showPasswords.value = false;
        toast.success('Password changed');
    }
}
</script>

<template>
    <div>
        <!-- Header: who you are -->
        <ListHero :title="auth.user?.name ?? 'My profile'" :description="auth.user?.email" :icon="UserRound" tone="#60a5fa" :photo="headerPhoto" photo-position="80% 40%">
            <template #actions>
                <span v-for="role in auth.user?.roles ?? []" :key="role" class="pf-chip" :class="{ 'is-super': role === 'super-admin' }">
                    <component :is="role === 'super-admin' ? Crown : ShieldCheck" class="size-4" aria-hidden="true" />{{ roleLabel(role) }}
                </span>
                <span class="pf-chip"><Clock class="size-4" aria-hidden="true" />Last sign-in: {{ lastSignIn }}</span>
            </template>
        </ListHero>

        <div class="grid grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
            <!-- Account details -->
            <form class="card p-5 sm:p-6" novalidate @submit.prevent="saveProfile">
                <div class="flex items-center gap-3.5">
                    <span class="pf-avatar" aria-hidden="true">{{ initials(profile.data.name || auth.user?.name || '?') }}</span>
                    <div class="min-w-0">
                        <h2 class="text-[16px] font-bold text-ink">Account details</h2>
                        <p class="text-[13px] text-ink-soft">Your name, sign-in email and phone number.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-4">
                    <AppInput v-model="profile.data.name" label="Full name" autocomplete="name" required :error="profile.error('name')" />
                    <AppInput v-model="profile.data.email" label="Email address" type="email" autocomplete="email" hint="You sign in with this email." required :error="profile.error('email')" />
                    <FormField label="Phone" for="profile-phone">
                        <PhoneInput id="profile-phone" v-model="profile.data.phone" :error="profile.error('phone')" />
                    </FormField>
                </div>
                <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
                    <span v-if="profileDirty" class="mr-auto inline-flex items-center gap-1.5 text-[13px] font-semibold text-amber-700"><span class="size-1.5 rounded-full bg-amber-500" aria-hidden="true" />Unsaved changes</span>
                    <AppButton v-if="profileDirty" variant="ghost" :icon="Undo2" :disabled="profile.processing" @click="profile.reset(original())">Undo</AppButton>
                    <AppButton type="submit" :loading="profile.processing" :disabled="!profileDirty">Save changes</AppButton>
                </div>
            </form>

            <!-- Password -->
            <form class="card p-5 sm:p-6" novalidate @submit.prevent="savePassword">
                <div class="flex items-center gap-3.5">
                    <span class="ph-tile flex size-12 shrink-0 items-center justify-center rounded-2xl" style="--tone: #fbbf24" aria-hidden="true"><KeyRound class="size-6" /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-[16px] font-bold text-ink">Change password</h2>
                        <p class="text-[13px] text-ink-soft">You stay signed in on this device.</p>
                    </div>
                    <button type="button" class="pf-reveal" :aria-pressed="showPasswords" @click="showPasswords = !showPasswords">
                        <component :is="showPasswords ? EyeOff : Eye" class="size-4" aria-hidden="true" />{{ showPasswords ? 'Hide' : 'Show' }}
                    </button>
                </div>
                <div class="mt-5 space-y-4">
                    <AppInput v-model="password.data.current_password" label="Current password" :type="showPasswords ? 'text' : 'password'" autocomplete="current-password" required :error="password.error('current_password')" />
                    <AppInput v-model="password.data.password" label="New password" :type="showPasswords ? 'text' : 'password'" autocomplete="new-password" required :error="password.error('password')" />
                    <AppInput v-model="password.data.password_confirmation" label="Confirm new password" :type="showPasswords ? 'text' : 'password'" autocomplete="new-password" required />
                </div>

                <!-- Live check against the rules -->
                <div class="mt-4 rounded-2xl bg-canvas p-4">
                    <div class="pf-strength" :data-level="strength" aria-hidden="true"><span v-for="n in 4" :key="n" :class="{ 'is-on': n <= strength }" /></div>
                    <ul class="mt-3 grid gap-1.5 sm:grid-cols-2" aria-label="Password rules">
                        <li v-for="rule in rules" :key="rule.label" class="flex items-center gap-2 text-[13px]" :class="rule.ok ? 'font-semibold text-emerald-700' : 'text-ink-soft'">
                            <component :is="rule.ok ? Check : Circle" class="size-4 shrink-0" :stroke-width="rule.ok ? 3 : 2" aria-hidden="true" />{{ rule.label }}<span class="sr-only">{{ rule.ok ? ': done' : ': not yet' }}</span>
                        </li>
                    </ul>
                </div>

                <div class="mt-6 flex justify-end">
                    <AppButton type="submit" :icon="LockKeyhole" :loading="password.processing" :disabled="!canChangePassword">Update password</AppButton>
                </div>
            </form>
        </div>

        <!-- What you can open -->
        <section class="card mt-5 p-5 sm:p-6" aria-labelledby="pf-access">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="pf-access" class="text-[16px] font-bold text-ink">Your access</h2>
                <p class="text-[13px] text-ink-soft">{{ auth.user?.is_super_admin ? 'Super admins can open and change everything.' : 'Ask a super admin if you need more.' }}</p>
            </div>
            <ul class="pf-access mt-4">
                <li v-for="item in access" :key="item.to" class="pf-module" :style="{ '--tone': item.tone }">
                    <span class="pf-module-icon" aria-hidden="true"><component :is="item.icon" class="size-[18px]" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[14px] font-semibold text-ink">{{ item.label }}</span>
                        <span class="block text-[12px] text-ink-soft">{{ item.actions[0] === 'all' || !item.permission ? 'Full access' : item.canEdit ? item.actions.map(titleCase).join(', ') : 'View only' }}</span>
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>

<style scoped>
.pf-chip {
    display: inline-flex;
    min-height: 2.25rem;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    background: #fff;
    padding: 0 0.85rem;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-ink);
    box-shadow: inset 0 0 0 1px var(--color-line);

    &.is-super {
        background: #fef3c7;
        color: #92400e;
        box-shadow: inset 0 0 0 1px #fde68a;
    }
}

.pf-avatar {
    display: flex;
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: linear-gradient(135deg, color-mix(in oklab, #60a5fa 40%, white), color-mix(in oklab, #60a5fa 16%, white));
    color: color-mix(in oklab, #3b82f6 55%, black);
    font-size: 16px;
    font-weight: 700;
}

.pf-reveal {
    display: inline-flex;
    min-height: 2.5rem;
    align-items: center;
    gap: 0.35rem;
    border-radius: 0.75rem;
    border: 1px solid var(--color-line);
    padding: 0 0.75rem;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-ink-soft);

    &:hover {
        background: var(--color-canvas);
        color: var(--color-ink);
    }
}

.pf-strength {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.35rem;

    > span {
        height: 0.4rem;
        border-radius: 999px;
        background: #e2e8f0;
        transition: background-color 250ms ease;
    }

    &[data-level='1'] > .is-on {
        background: #f87171;
    }
    &[data-level='2'] > .is-on {
        background: #fb923c;
    }
    &[data-level='3'] > .is-on {
        background: #facc15;
    }
    &[data-level='4'] > .is-on {
        background: #22c55e;
    }
}

.pf-access {
    display: grid;
    gap: 0.65rem;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 210px), 1fr));
}

.pf-module {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.9rem;
    border: 1px solid var(--color-line);
    padding: 0.65rem 0.75rem;
}

.pf-module-icon {
    display: flex;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.65rem;
    background: color-mix(in oklab, var(--tone) 18%, white);
    color: color-mix(in oklab, var(--tone) 40%, black);
}

@media (prefers-reduced-motion: reduce) {
    .pf-strength > span {
        transition: none;
    }
}
</style>
