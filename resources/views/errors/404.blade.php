@php(seo()->title(__('Page not found'))->noindex())
<x-site.layout>
    <x-site.section tone="sand" pattern>
        <div class="mx-auto max-w-xl py-10 text-center" data-reveal>
            <p class="font-display text-[7rem] leading-none font-semibold text-gold-deep" aria-hidden="true">404</p>
            <h1 class="mt-6 text-display-md text-brand">{{ __('This page could not be found') }}</h1>
            <p class="mt-4 text-ink-2">{{ __('The page may have moved, or the link may be out of date. Let us help you find your way.') }}</p>
            <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                <x-site.button :href="url('/')" icon="home">{{ __('Back to home') }}</x-site.button>
                <x-site.button :href="url('/contact')" variant="outline">{{ __('Contact us') }}</x-site.button>
            </div>
        </div>
    </x-site.section>
</x-site.layout>
