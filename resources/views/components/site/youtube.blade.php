@props(['id', 'title' => null, 'rounded' => 'rounded-2xl'])
{{-- YouTube facade: a thumbnail and play button; the player only loads when pressed. --}}
<div x-data="{ playing: false }" {{ $attributes->class(['relative aspect-video overflow-hidden bg-brand-950', $rounded]) }}>
    <template x-if="playing">
        <iframe class="absolute inset-0 size-full" src="https://www.youtube-nocookie.com/embed/{{ $id }}?autoplay=1&rel=0" title="{{ $title ?: __('Video') }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
    </template>
    <button type="button" x-show="!playing" @click="playing = true" class="group absolute inset-0 size-full" aria-label="{{ __('Play video') }}{{ $title ? ': '.$title : '' }}">
        <img src="https://i.ytimg.com/vi/{{ $id }}/hqdefault.jpg" alt="" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover opacity-90 transition-transform duration-700 group-hover:scale-[1.03]">
        <span class="absolute inset-0 bg-gradient-to-t from-brand-950/60 to-transparent"></span>
        <span class="absolute top-1/2 left-1/2 flex size-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-gold text-brand-950 shadow-xl transition-transform duration-300 group-hover:scale-110">
            <x-site.icon name="play" class="ml-0.5 size-6 fill-current" />
        </span>
    </button>
</div>
