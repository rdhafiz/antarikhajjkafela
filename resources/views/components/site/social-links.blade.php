@props(['label' => true])
{{--
    The social profiles (Settings → Social) plus WhatsApp, as round buttons
    in each network's own colour so they are easy to spot. Nothing renders
    when none are set.
--}}
@inject('site', App\Support\SiteContext::class)
@php
    $links = $site->socials();
    if ($whatsapp = $site->whatsappUrl()) {
        array_unshift($links, ['key' => 'whatsapp', 'label' => 'WhatsApp', 'url' => $whatsapp]);
    }
@endphp
@if ($links)
    <div {{ $attributes }}>
        @if ($label)
            <p class="text-[12px] font-semibold tracking-[0.16em] text-gold uppercase">{{ __('Follow us') }}</p>
        @endif
        <ul @class(['flex flex-wrap gap-2.5', 'mt-3' => $label])>
            @foreach ($links as $link)
                <li>
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" aria-label="{{ $link['label'] }}" title="{{ $link['label'] }}"
                       class="social-btn social-{{ $link['key'] }}">
                        <x-site.icon :name="$link['key']" class="size-5" />
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
