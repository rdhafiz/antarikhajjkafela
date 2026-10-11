@props(['languages', 'tone' => 'light'])
{{-- Segmented control listing every language; the page URL stays the same. --}}
<div
    role="group"
    aria-label="{{ __('Language') }}"
    @class([
        'inline-flex items-center rounded-full p-0.5',
        'bg-white/8 ring-1 ring-white/15' => $tone === 'dark',
        'bg-sand-100 ring-1 ring-line' => $tone === 'light',
    ])
>
    @foreach ($languages as $language)
        <a
            href="{{ $language['url'] }}"
            rel="nofollow"
            hreflang="{{ $language['code'] }}"
            lang="{{ $language['code'] }}"
            @if ($language['active']) aria-current="true" @endif
            title="{{ $language['name'] }}"
            @class([
                'rounded-full px-3 py-1 text-[13px] leading-5 font-semibold transition-colors',
                'bg-gold text-brand-950' => $language['active'],
                'text-white/70 hover:text-white' => ! $language['active'] && $tone === 'dark',
                'text-ink-3 hover:text-brand' => ! $language['active'] && $tone === 'light',
            ])
            {{-- System font, so showing "বাংলা" on English pages doesn't download a Bangla web font. --}}
            style="font-family: system-ui, sans-serif"
        >{{ $language['code'] === 'en' ? 'EN' : $language['native'] }}</a>
    @endforeach
</div>
