<script setup lang="ts">
import brandIcon from '@admin/assets/brand-icon.webp';

/*
 * Rosette of eight-point stars (two squares, one turned 45°), echoing the logo's frame.
 * Each ring draws itself on start-up, then turns very slowly; rings alternate direction.
 */
const rings = [
    { r: 78, star: true, spin: 'cw', speed: 90, delay: 0.15 },
    { r: 136, star: false, dashed: true, spin: 'ccw', speed: 140, delay: 0.3 },
    { r: 170, star: true, spin: 'ccw', speed: 120, delay: 0.45 },
    { r: 228, star: false, spin: 'cw', speed: 0, delay: 0.6 },
    { r: 292, star: true, spin: 'cw', speed: 160, delay: 0.75 },
    { r: 360, star: false, dashed: true, spin: 'ccw', speed: 200, delay: 0.9 },
];
const half = (r: number) => r / Math.SQRT2;
const year = new Date().getFullYear();
</script>

<template>
    <div class="ahk-scene relative isolate min-h-dvh overflow-hidden bg-[#06182e] text-white">
        <!-- Light and texture -->
        <div class="ahk-glow pointer-events-none absolute -z-10" aria-hidden="true" />
        <div class="ahk-lattice pointer-events-none absolute inset-0 -z-10 opacity-[0.05]" aria-hidden="true" />

        <div class="mx-auto grid min-h-dvh max-w-[1280px] lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:items-center lg:gap-10 lg:px-10">
            <!-- Brand: the rosette with the logo at its centre -->
            <header class="relative flex flex-col items-center justify-center px-6 pt-9 pb-16 lg:min-h-dvh lg:py-16">
                <div class="relative aspect-square w-[min(66vw,270px)] sm:w-[min(60vw,340px)] lg:w-[min(46vw,560px)]">
                    <svg viewBox="-380 -380 760 760" class="absolute inset-0 size-full overflow-visible" fill="none" aria-hidden="true">
                        <g v-for="(ring, i) in rings" :key="i" class="ahk-ring" :class="ring.speed ? `ahk-spin-${ring.spin}` : ''" :style="{ '--spin': `${ring.speed}s` }">
                            <template v-if="ring.star">
                                <rect class="ahk-draw" pathLength="1" :x="-half(ring.r)" :y="-half(ring.r)" :width="half(ring.r) * 2" :height="half(ring.r) * 2" :style="{ '--d': `${ring.delay}s` }" />
                                <rect class="ahk-draw" pathLength="1" :x="-half(ring.r)" :y="-half(ring.r)" :width="half(ring.r) * 2" :height="half(ring.r) * 2" transform="rotate(45)" :style="{ '--d': `${ring.delay + 0.1}s` }" />
                            </template>
                            <circle v-else class="ahk-draw" :class="{ 'ahk-dashed': ring.dashed }" pathLength="1" :r="ring.r" :style="{ '--d': `${ring.delay}s` }" />
                        </g>
                        <circle class="ahk-draw ahk-gold" pathLength="1" r="150" style="--d: 0s" />
                    </svg>

                    <div class="ahk-logo absolute top-1/2 left-1/2 flex size-[26%] -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-[28%] bg-white p-[4%] shadow-[0_20px_60px_-20px_rgb(0_0_0/0.6)]">
                        <img :src="brandIcon" alt="" class="size-full object-contain" />
                    </div>
                </div>

                <div class="ahk-rise mt-6 text-center lg:mt-10" style="--d: 1.05s">
                    <p class="text-[13px] font-semibold tracking-[0.24em] text-gold uppercase">Antarik Hajj Kafela</p>
                    <p class="mx-auto mt-3 hidden max-w-sm text-[15px] leading-relaxed text-white/60 lg:block">
                        Packages, bookings and every page of the website, managed from one place.
                    </p>
                </div>
            </header>

            <!-- Sign-in card: a bottom sheet on phones, a floating card on wide screens -->
            <main class="ahk-card relative z-10 -mt-10 rounded-t-[30px] bg-white px-6 pt-9 pb-10 text-ink shadow-[0_-20px_60px_-30px_rgb(0_0_0/0.6)] sm:px-10 lg:mt-0 lg:rounded-[30px] lg:px-11 lg:py-12 lg:shadow-[0_40px_100px_-40px_rgb(0_0_0/0.75)]">
                <div class="mx-auto w-full max-w-[400px]">
                    <RouterView />
                </div>
                <p class="ahk-rise mt-10 text-center text-[12px] text-ink-soft" style="--d: 1.35s">© {{ year }} Antarik Hajj Kafela</p>
            </main>
        </div>
    </div>
</template>
