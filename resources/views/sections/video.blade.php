@php
    preg_match('#(?:v=|embed/|shorts/|youtu\.be/)([\w-]{6,})#', (string) $c['url'], $m);
    $videoId = $m[1] ?? null;
    $split = ($c['layout'] ?? 'centered') === 'split';
    $poster = $c['poster']?->url('lg') ?? ($videoId ? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg" : null);
@endphp
@if ($videoId)
    <x-site.block :style="$style" :section="$section">
        <div @class(['grid items-center gap-12', 'lg:grid-cols-[0.85fr_1.15fr] lg:gap-16' => $split])>
            <x-site.block-heading :c="$c" :align="$split ? 'left' : 'center'" />

            {{-- The YouTube player only loads after the visitor presses play. --}}
            <div x-data="{ playing: false }" @class(['relative mx-auto w-full', 'max-w-4xl' => ! $split]) data-reveal>
                <div class="relative aspect-video overflow-hidden rounded-[1.5rem] bg-brand-950 shadow-[var(--shadow-lift)]">
                    <template x-if="playing">
                        <iframe
                            class="absolute inset-0 size-full"
                            src="https://www.youtube-nocookie.com/embed/{{ $videoId }}?autoplay=1&rel=0"
                            title="{{ $c['title'] ?? 'Video' }}"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </template>
                    <button type="button" x-show="!playing" @click="playing = true" class="group absolute inset-0 size-full" aria-label="{{ __('Play video') }}: {{ $c['title'] }}">
                        @if ($poster)
                            <img src="{{ $poster }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover opacity-85 transition-transform duration-700 group-hover:scale-[1.03]">
                        @endif
                        <span class="absolute inset-0 bg-gradient-to-t from-brand-950/70 to-brand-950/10"></span>
                        <span class="absolute top-1/2 left-1/2 flex size-20 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-gold text-brand-950 shadow-2xl transition-transform duration-300 group-hover:scale-110">
                            <span class="absolute inset-0 animate-ping rounded-full bg-gold/40 motion-reduce:hidden"></span>
                            <x-site.icon name="play" class="relative ml-1 size-8 fill-current" />
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </x-site.block>
@endif
