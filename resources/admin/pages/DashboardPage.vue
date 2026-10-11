<script setup lang="ts">
import { computed } from 'vue';
import { ArrowUpRight, Images, ShieldCheck, Users } from 'lucide-vue-next';
import { useAuthStore } from '@admin/stores/auth';
import { useAppStore } from '@admin/stores/app';

const auth = useAuthStore();
const app = useAppStore();

const greeting = computed(() => {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
});

const shortcuts = computed(() =>
    [
        { label: 'Media library', description: 'Upload and organise images', to: '/admin/media', icon: Images, permission: 'media.view' },
        { label: 'Users', description: 'Manage admin accounts', to: '/admin/users', icon: Users, permission: 'users.view' },
        { label: 'Roles & permissions', description: 'Control who can do what', to: '/admin/roles', icon: ShieldCheck, permission: 'roles.view' },
    ].filter((item) => auth.can(item.permission)),
);
</script>

<template>
    <div>
        <section class="relative overflow-hidden rounded-2xl bg-brand px-6 py-8 text-white sm:px-8">
            <div class="absolute -top-24 -right-16 size-72 rounded-full bg-gold/15 blur-2xl" aria-hidden="true" />
            <p class="relative text-[13px] font-semibold tracking-wider text-gold uppercase">{{ greeting }}</p>
            <h1 class="relative mt-1.5 text-2xl font-bold tracking-[-0.01em] sm:text-[28px]">{{ auth.user?.name }}</h1>
            <p class="relative mt-2 max-w-xl text-sm text-white/70">
                Welcome to the {{ app.name }} admin portal. Booking and content statistics will appear here as those modules come online.
            </p>
        </section>

        <h2 class="mt-8 mb-3 text-[13px] font-bold tracking-wider text-ink-muted uppercase">Quick access</h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <RouterLink
                v-for="item in shortcuts"
                :key="item.to"
                :to="item.to"
                class="card group flex items-start gap-4 p-5 transition-[border-color,box-shadow] hover:border-brand/30 hover:shadow-[var(--shadow-pop)]"
            >
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
                    <component :is="item.icon" class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[15px] font-semibold text-ink">{{ item.label }}</span>
                    <span class="mt-0.5 block text-[13px] text-ink-muted">{{ item.description }}</span>
                </span>
                <ArrowUpRight class="size-4 text-ink-muted transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
            </RouterLink>
        </div>
    </div>
</template>
