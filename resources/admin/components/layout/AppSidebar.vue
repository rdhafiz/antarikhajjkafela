<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import brandIcon from '@admin/assets/brand-icon.webp';
import { navigation, type NavItem } from '@admin/navigation';
import { useAuthStore } from '@admin/stores/auth';
import { useAppStore } from '@admin/stores/app';

defineProps<{ collapsed?: boolean }>();
const emit = defineEmits<{ navigate: [] }>();

const auth = useAuthStore();
const app = useAppStore();
const route = useRoute();

const sections = computed(() =>
    navigation
        .map((section) => ({ ...section, items: section.items.filter((item) => auth.can(item.permission)) }))
        .filter((section) => section.items.length > 0),
);

/** Running index of each item, so the start-up reveal can stagger them top to bottom. */
const order = computed(() => {
    const map = new Map<string, number>();
    let i = 0;
    for (const section of sections.value) for (const item of section.items) map.set(item.to, i++);
    return map;
});

function isActive(item: NavItem): boolean {
    return item.to === '/admin' ? route.path === '/admin' : route.path.startsWith(item.to);
}
</script>

<template>
    <nav class="sb relative isolate flex h-full flex-col overflow-hidden text-sidebar-text" :class="{ 'is-collapsed': collapsed }" aria-label="Main">
        <div class="sb-lattice pointer-events-none absolute inset-0 -z-10" aria-hidden="true" />

        <!-- Brand -->
        <RouterLink to="/admin" class="sb-brand sb-in flex h-16 shrink-0 items-center gap-3 px-4 lg:h-[72px]" :class="{ 'justify-center px-0': collapsed }" style="--i: 0" @click="emit('navigate')">
            <span class="sb-mark relative flex size-10 shrink-0 items-center justify-center rounded-xl bg-white p-1.5">
                <img :src="app.icon ?? brandIcon" alt="" class="size-full object-contain" />
            </span>
            <span v-if="!collapsed" class="min-w-0 leading-tight">
                <span class="block truncate text-[14.5px] font-bold text-white">{{ app.name }}</span>
                <span class="mt-0.5 flex items-center gap-1.5 text-[11px] font-semibold tracking-[0.16em] text-sidebar-accent uppercase">
                    <span class="size-1.5 rounded-full bg-sidebar-accent" aria-hidden="true" />Admin portal
                </span>
            </span>
        </RouterLink>

        <!-- Navigation -->
        <div class="sb-scroll flex-1 overflow-y-auto px-3 pt-3 pb-3">
            <div v-for="(section, s) in sections" :key="s" class="mb-3 last:mb-0">
                <p v-if="section.label && !collapsed" class="sb-in mb-1 px-3 text-[10.5px] font-bold tracking-[0.16em] text-sidebar-text/75 uppercase" :style="{ '--i': (order.get(section.items[0].to) ?? 0) + 1 }">
                    {{ section.label }}
                </p>
                <div v-else-if="section.label" class="mx-auto mb-2 h-px w-8 bg-white/10" aria-hidden="true" />

                <ul class="space-y-0.5">
                    <li v-for="item in section.items" :key="item.to" class="sb-in" :style="{ '--i': (order.get(item.to) ?? 0) + 1 }">
                        <RouterLink
                            :to="item.to"
                            class="sb-link group relative flex h-10 items-center gap-3 rounded-xl px-2"
                            :class="[{ 'is-active': isActive(item) }, collapsed && 'justify-center px-0']"
                            :style="{ '--tone': item.tone }"
                            :title="collapsed ? item.label : undefined"
                            :aria-current="isActive(item) ? 'page' : undefined"
                            @click="emit('navigate')"
                        >
                            <span class="sb-tile flex size-8 shrink-0 items-center justify-center rounded-[10px]">
                                <component :is="item.icon" class="size-[17px]" aria-hidden="true" />
                            </span>
                            <span v-if="!collapsed" class="flex-1 truncate text-[14px] font-medium">{{ item.label }}</span>
                            <span v-else class="sr-only">{{ item.label }}</span>
                            <!-- Unread = not opened yet (the dashboard's "to handle" counts status New instead). -->
                            <span
                                v-if="item.badge && app[item.badge] > 0"
                                :title="`${app[item.badge]} unread (not opened yet)`"
                                class="sb-badge flex h-5 min-w-5 items-center justify-center rounded-full px-1.5 text-[11px] font-bold"
                                :class="collapsed && 'absolute top-0.5 right-1.5 h-4 min-w-4 px-1 text-[10px]'"
                            >
                                {{ app[item.badge] > 99 ? '99+' : app[item.badge] }}
                                <span class="sr-only">unread</span>
                            </span>
                        </RouterLink>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Credit -->
        <div class="sb-in sb-credit m-3 mt-0 shrink-0 rounded-2xl px-3 py-3 text-center" :class="{ 'px-0': collapsed }" :style="{ '--i': order.size + 2 }">
            <a v-if="collapsed" href="https://codevioso.com/" target="_blank" rel="noopener" class="mx-auto flex size-9 items-center justify-center rounded-xl text-[13px] font-bold text-sidebar-accent hover:bg-white/10" title="Designed and Developed by Codevioso">
                CV<span class="sr-only">Designed and Developed by Codevioso (opens in a new tab)</span>
            </a>
            <p v-else class="text-[12px] leading-relaxed text-sidebar-text/80">
                Designed and Developed by
                <a href="https://codevioso.com/" target="_blank" rel="noopener" class="font-semibold text-sidebar-accent underline-offset-2 hover:underline">Codevioso</a>
            </p>
        </div>
    </nav>
</template>
