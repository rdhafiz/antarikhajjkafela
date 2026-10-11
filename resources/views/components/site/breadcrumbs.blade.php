@props(['items' => null, 'light' => false])
@php($items ??= seo()->breadcrumbs)
@if (count($items) > 1)
    <nav aria-label="{{ __('Breadcrumb') }}" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px]">
            @foreach ($items as $item)
                <li class="flex items-center gap-2">
                    @if (! $loop->first)
                        <x-site.icon name="chevron-right" @class(['size-3.5', 'text-white/35' => $light, 'text-ink-3/60' => ! $light]) />
                    @endif
                    @if ($item['url'] && ! $loop->last)
                        <a href="{{ $item['url'] }}" @class(['transition-colors', 'text-white/65 hover:text-gold' => $light, 'text-ink-3 hover:text-brand' => ! $light])>{{ $item['label'] }}</a>
                    @else
                        <span aria-current="page" @class(['font-semibold', 'text-white' => $light, 'text-ink' => ! $light])>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
