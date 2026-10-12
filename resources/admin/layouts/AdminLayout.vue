<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useLocalStorage } from '@vueuse/core';
import { DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle, VisuallyHidden } from 'reka-ui';
import AppSidebar from '@admin/components/layout/AppSidebar.vue';
import AppTopbar from '@admin/components/layout/AppTopbar.vue';
import { useAppStore } from '@admin/stores/app';

const collapsed = useLocalStorage('admin.sidebar.collapsed', false);
const mobileOpen = ref(false);
const route = useRoute();
const app = useAppStore();
const year = new Date().getFullYear();

watch(() => route.fullPath, () => (mobileOpen.value = false));
</script>

<template>
    <div class="min-h-dvh">
        <!-- Desktop sidebar: full height, attached to the left edge -->
        <aside class="sb-shell fixed inset-y-0 left-0 z-40 hidden lg:block" :class="collapsed ? 'w-[76px]' : 'w-[264px]'">
            <AppSidebar :collapsed="collapsed" />
        </aside>

        <!-- Mobile drawer -->
        <DialogRoot v-model:open="mobileOpen">
            <DialogPortal>
                <DialogOverlay class="sb-overlay fixed inset-0 z-50 bg-[#06182e]/55 backdrop-blur-[2px] lg:hidden" />
                <DialogContent class="sb-drawer fixed inset-y-0 left-0 z-50 w-[288px] max-w-[85vw] outline-none lg:hidden">
                    <VisuallyHidden><DialogTitle>Navigation</DialogTitle></VisuallyHidden>
                    <AppSidebar @navigate="mobileOpen = false" />
                </DialogContent>
            </DialogPortal>
        </DialogRoot>

        <div class="flex min-h-dvh flex-col transition-[padding] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]" :class="collapsed ? 'lg:pl-[76px]' : 'lg:pl-[264px]'">
            <AppTopbar :collapsed="collapsed" @toggle-collapse="collapsed = !collapsed" @open-menu="mobileOpen = true" />
            <main class="mx-auto w-full max-w-[1400px] flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <RouterView v-slot="{ Component }">
                    <Transition
                        mode="out-in"
                        enter-from-class="opacity-0 translate-y-1"
                        enter-active-class="transition duration-200 ease-out"
                        leave-to-class="opacity-0"
                        leave-active-class="transition duration-100 ease-in"
                    >
                        <component :is="Component" :key="String(route.name)" />
                    </Transition>
                </RouterView>
            </main>

            <footer class="mx-auto flex w-full max-w-[1400px] flex-col items-center justify-between gap-1.5 px-4 pt-2 pb-6 text-[12.5px] text-ink-soft sm:flex-row sm:px-6 lg:px-8">
                <p>© {{ year }} {{ app.name }}</p>
                <p>
                    Designed and Developed by
                    <a href="https://codevioso.com/" target="_blank" rel="noopener" class="font-semibold text-brand underline-offset-2 hover:underline">Codevioso</a>
                </p>
            </footer>
        </div>
    </div>
</template>
