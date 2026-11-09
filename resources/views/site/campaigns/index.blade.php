@inject('site', App\Support\SiteContext::class)
@php
    use App\Domain\Blog\PostPresenter;
    use App\Support\Money;

    $intro = __('Limited-time Hajj, Umrah and travel offers from Antarik.');
    $whatsapp = $site->whatsappUrl(__('Assalamu alaikum, I would like to know about your current offers.'));
    $endsFirst = $campaigns->pluck('expires_at')->filter()->min();
@endphp
{{-- Laid out like a service page (/umrah): photo hero with key figures, then the offers on a sky band. --}}
<x-site.layout header="overlay">
    <x-site.hero
        :eyebrow="__('Campaigns')"
        :title="__('Special offers')"
        :intro="$intro"
        :stats="[
            ['value' => $campaigns->isNotEmpty() ? Money::digits($campaigns->count()) : null, 'label' => __('Offers')],
            ['value' => $endsFirst ? PostPresenter::date($endsFirst) : null, 'label' => __('Ending soonest'), 'small' => true],
        ]"
    >
        <div class="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
            @if ($campaigns->isNotEmpty())
                <x-site.button href="#offers" variant="gold" size="lg" icon-right="chevron-down">{{ __('View offers') }}</x-site.button>
            @endif
            @if ($whatsapp)
                <x-site.button :href="$whatsapp" external variant="whatsapp" size="lg" icon="whatsapp">{{ __('Ask on WhatsApp') }}</x-site.button>
            @else
                <x-site.button :href="url('/contact')" variant="outline-light" size="lg">{{ __('Contact us') }}</x-site.button>
            @endif
        </div>
    </x-site.hero>

    <section id="offers" class="section-light relative isolate scroll-mt-20 overflow-hidden bg-sky-soft py-20 sm:py-24 lg:py-28">
        <x-site.decor />
        <div class="container-site">
            <div class="mx-auto mb-14 max-w-2xl text-center" data-reveal>
                <p class="eyebrow justify-center">{{ __('Campaigns') }}</p>
                <h2 class="mt-4 text-display-lg text-brand">{{ __('Choose your offer') }}</h2>
                <p class="mt-5 text-[1.0625rem] leading-relaxed text-ink-2">{{ $intro }}</p>
            </div>

            @if ($campaigns->isEmpty())
                <div class="card-site mx-auto flex max-w-xl flex-col items-center px-6 py-16 text-center" data-reveal>
                    <x-site.icon name="calendar" class="size-10 text-gold-deep" />
                    <h3 class="mt-5 font-display text-display-sm text-brand">{{ __('No offers running right now') }}</h3>
                    <p class="mt-3 max-w-md text-ink-2">{{ __('Call or message us and we will share the latest offers with you.') }}</p>
                </div>
            @else
                <div class="mx-auto grid max-w-6xl gap-8">
                    @foreach ($campaigns as $page)
                        <x-site.campaign-card :page="$page" :image="$images[$page->seo['image'] ?? 0] ?? null" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-site.layout>
