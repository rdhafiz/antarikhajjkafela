{{--
    An error page inside the normal site (header, menu, footer, the visitor's
    language). Expects $code, $title, $text. The 4xx pages render it and fall
    back to the standalone minimal layout when the site itself cannot render.
--}}
@php(seo()->title($title)->noindex())
<x-site.layout>
    <x-site.section tone="sand" pattern>
        <div class="mx-auto max-w-xl py-10 text-center" data-reveal>
            <p class="font-display text-[7rem] leading-none font-semibold text-gold-deep" aria-hidden="true">{{ App\Support\Money::digits($code) }}</p>
            <h1 class="mt-6 text-display-md text-brand">{{ $title }}</h1>
            <p class="mt-4 text-ink-2">{{ $text }}</p>
            <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                <x-site.button :href="url('/')" icon="home">{{ __('Back to home') }}</x-site.button>
                <x-site.button :href="url('/contact')" variant="outline">{{ __('Contact us') }}</x-site.button>
            </div>
        </div>
    </x-site.section>
</x-site.layout>
