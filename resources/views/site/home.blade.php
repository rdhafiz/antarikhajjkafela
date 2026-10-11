{{-- Interim home page; replaced by the page-builder home in the content management phase. --}}
@inject('site', App\Support\SiteContext::class)
<x-site.layout header="overlay">
    <section class="relative isolate overflow-hidden bg-gradient-to-br from-brand via-brand-900 to-brand-950 pt-20 text-white">
        <div class="pattern-star absolute inset-0 -z-10 opacity-[0.07]" aria-hidden="true"></div>
        <div class="absolute top-1/3 -right-40 -z-10 size-[640px] rounded-full bg-gold/12 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-48 -left-40 -z-10 size-[520px] rounded-full bg-emerald/20 blur-3xl" aria-hidden="true"></div>

        <div class="container-site grid items-center gap-14 py-20 sm:py-24 lg:grid-cols-[1.15fr_0.85fr] lg:py-32">
            <div data-reveal>
                <p class="eyebrow eyebrow-light">{{ __('Since 2016 · Dhaka, Bangladesh') }}</p>
                <h1 class="mt-5 text-display-xl text-white">
                    {{ __('A sacred journey,') }}
                    <span class="text-gold italic">{{ __('guided with care.') }}</span>
                </h1>
                <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/75">{{ setting_t('branding.short_description') }}</p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <x-site.button :href="url('/hajj')" variant="gold" size="lg" icon-right="arrow-right">{{ __('Explore Hajj packages') }}</x-site.button>
                    <x-site.button :href="url('/umrah')" variant="outline-light" size="lg">{{ __('Umrah packages') }}</x-site.button>
                </div>
            </div>

            <div class="relative hidden lg:block" data-reveal style="--reveal-delay: 120ms">
                <div class="relative mx-auto aspect-square w-full max-w-md">
                    <div class="absolute inset-0 rotate-45 rounded-[3rem] border border-gold/30"></div>
                    <div class="absolute inset-8 rounded-[2.5rem] border border-white/10 bg-white/[0.04] backdrop-blur-sm"></div>
                    <img src="{{ app(App\Support\Branding::class)->iconUrl(512) }}" alt="" class="absolute inset-[22%] size-[56%] object-contain drop-shadow-2xl" width="512" height="512" loading="lazy" decoding="async">
                </div>
            </div>
        </div>

        <div class="relative border-t border-white/10 bg-brand-950/50">
            <dl class="container-site grid grid-cols-2 gap-y-6 py-8 sm:grid-cols-4">
                @foreach ([['1500+', __('Pilgrims served')], ['10', __('Shariah consultants')], ['10', __('Hajj & Umrah guides')], ['2016', __('Serving since')]] as [$value, $label])
                    <div class="flex flex-col-reverse items-center text-center sm:border-l sm:border-white/10 sm:first:border-l-0">
                        <dt class="mt-1 text-[13px] tracking-wide text-white/60">{{ $label }}</dt>
                        <dd class="font-display text-3xl font-semibold text-gold">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <x-site.section tone="sand" pattern>
        <x-site.section-heading
            :eyebrow="__('Our promise')"
            :title="__('Hajj and Umrah, arranged with knowledge and sincerity')"
            :intro="__('From visa and flights to hotels near the Haram and guidance from experienced scholars, every step of your pilgrimage is handled by people who understand its meaning.')"
        />
        <div class="mt-14 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['shield', __('Licensed & transparent'), __('Clear pricing and official payment channels, with no hidden costs.')],
                ['book', __('Scholar-guided'), __('Workshops before departure and scholars with you throughout the journey.')],
                ['building', __('Close to the Haram'), __('Carefully chosen hotels in Makkah and Madinah for every budget.')],
            ] as $i => [$icon, $title, $text])
                <div class="card-site p-7" data-reveal style="--reveal-delay: {{ $i * 90 }}ms">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand ring-1 ring-brand-100">
                        <x-site.icon :name="$icon" class="size-6" />
                    </span>
                    <h3 class="mt-6 text-display-sm text-brand">{{ $title }}</h3>
                    <p class="mt-3 text-ink-2">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </x-site.section>
</x-site.layout>
