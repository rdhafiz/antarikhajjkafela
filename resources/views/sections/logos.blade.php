@php($logos = array_values(array_filter($c['logos'] ?? [], fn ($l) => $l['image'])))
<x-site.block :style="$style" :section="$section">
    <x-site.block-heading :c="$c" class="mb-10" />
    <ul class="flex flex-wrap items-center justify-center gap-x-12 gap-y-8">
        @foreach ($logos as $logo)
            <li>
                <img
                    src="{{ $logo['image']->url('sm') }}"
                    alt="{{ $logo['name'] }}"
                    title="{{ $logo['name'] }}"
                    loading="lazy"
                    decoding="async"
                    @class(['h-10 w-auto max-w-40 object-contain sm:h-12', 'opacity-60 grayscale transition-[filter,opacity] duration-300 hover:opacity-100 hover:grayscale-0' => $c['grayscale']])
                >
            </li>
        @endforeach
    </ul>
</x-site.block>
