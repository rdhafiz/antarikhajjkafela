<script setup lang="ts">
import brandIcon from '@admin/assets/brand-icon.webp';
</script>

<template>
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
        <!-- Brand panel -->
        <aside class="relative hidden overflow-hidden bg-brand text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pattern absolute inset-0 opacity-[0.07]" aria-hidden="true" />
            <div class="absolute -right-40 -bottom-40 size-[520px] rounded-full bg-gold/10 blur-3xl" aria-hidden="true" />

            <div class="relative flex items-center gap-3">
                <span class="flex size-12 items-center justify-center rounded-xl bg-white p-1.5">
                    <img :src="brandIcon" alt="" class="size-full object-contain" />
                </span>
                <div class="leading-tight">
                    <p class="text-[15px] font-bold">Antarik Hajj Kafela</p>
                    <p class="text-[13px] text-white/60">Admin portal</p>
                </div>
            </div>

            <div class="relative max-w-md">
                <p class="mb-4 h-1 w-12 rounded-full bg-gold" />
                <p class="text-[34px] leading-[1.15] font-bold tracking-[-0.02em]">Serving the guests of Allah, with care in every detail.</p>
                <p class="mt-4 text-[15px] leading-relaxed text-white/65">
                    Manage packages, bookings and every page of the website from one place.
                </p>
            </div>

            <p class="relative text-[13px] text-white/45">© {{ new Date().getFullYear() }} Antarik Hajj Kafela</p>
        </aside>

        <!-- Form -->
        <main class="flex items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-[400px]">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <img :src="brandIcon" alt="" class="size-11 object-contain" />
                    <p class="text-[15px] font-bold text-ink">Antarik Hajj Kafela</p>
                </div>
                <RouterView />
            </div>
        </main>
    </div>
</template>

<style scoped>
/* Eight-point star lattice, echoing the logo's geometric frame. */
.pattern {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='88' height='88' viewBox='0 0 88 88'%3E%3Cg fill='none' stroke='%23fff' stroke-width='1.5'%3E%3Crect x='24' y='24' width='40' height='40'/%3E%3Crect x='24' y='24' width='40' height='40' transform='rotate(45 44 44)'/%3E%3C/g%3E%3C/svg%3E");
    background-size: 88px 88px;
}
</style>
