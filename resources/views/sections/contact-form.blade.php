@if (($c['layout'] ?? 'split') === 'concierge')
    @inject('site', App\Support\SiteContext::class)
    @php
        $phones = $site->phones();
        $emails = $site->emails();
        $whatsapp = $site->whatsappUrl();
        $address = $site->address();
        $map = $site->mapEmbedUrl();
        $locale = app()->getLocale();
        $headingTag = $first ? 'h1' : 'h2';

        // Each way to reach us as one row; rows that are not set are left out.
        $channels = array_values(array_filter([
            $phones ? ['icon' => 'phone', 'label' => __('Call us'), 'value' => collect($phones)->pluck('display')->implode(' · '), 'href' => $phones[0]['href'], 'ltr' => true] : null,
            $whatsapp ? ['icon' => 'whatsapp', 'label' => 'WhatsApp', 'value' => settings('contact.primary_whatsapp') ?: __('Chat on WhatsApp'), 'href' => $whatsapp, 'external' => true, 'ltr' => true] : null,
            $emails ? ['icon' => 'mail', 'label' => __('Email'), 'value' => $emails[0], 'href' => 'mailto:'.$emails[0], 'ltr' => true] : null,
            $address ? ['icon' => 'map-pin', 'label' => __('Visit our office'), 'value' => $address, 'href' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address), 'external' => true] : null,
        ]));
        $topics = [__('Hajj package'), __('Umrah package'), __('Visa'), __('Something else')];
    @endphp
    <x-site.block :style="$style" :section="$section">
        <div class="pointer-events-none absolute -top-40 -right-40 -z-10 size-[560px] rounded-full bg-gold/12 blur-3xl" data-parallax="-0.15" aria-hidden="true"></div>
        <div class="pattern-star-ink pointer-events-none absolute -inset-y-[20%] inset-x-0 -z-10 opacity-[0.025]" data-parallax="0.2" aria-hidden="true"></div>

        {{-- One composite panel: the ways to reach us on navy, the message form on white. --}}
        <div class="concierge mx-auto grid max-w-6xl overflow-hidden rounded-[2.25rem] bg-white shadow-[var(--shadow-lift)] ring-1 ring-line lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
            <div class="relative isolate flex flex-col overflow-hidden bg-brand-950 p-7 text-white sm:p-10 lg:p-12">
                <div class="pattern-star absolute inset-0 -z-10 opacity-[0.06]" aria-hidden="true"></div>
                <div class="concierge-orbit absolute -bottom-40 -left-40 -z-10 size-[26rem] rounded-full border-[28px] border-gold/[0.08]" aria-hidden="true"></div>

                <div data-reveal>
                    @if (filled($c['eyebrow']))<p class="eyebrow eyebrow-light">{{ $c['eyebrow'] }}</p>@endif
                    @if (filled($c['title']))
                        <{{ $headingTag }} @class(['text-display-lg text-white', 'mt-4' => filled($c['eyebrow'])])>{{ $c['title'] }}</{{ $headingTag }}>
                    @endif
                    @if (filled($c['intro']))<p class="mt-4 max-w-md text-[1.0625rem] leading-relaxed text-white/75">{{ $c['intro'] }}</p>@endif
                </div>

                @if ($channels)
                    <ul class="mt-9 space-y-3">
                        @foreach ($channels as $i => $channel)
                            <li data-reveal="left" style="--reveal-delay: {{ 100 + $i * 90 }}ms">
                                <a href="{{ $channel['href'] }}" @if (! empty($channel['external'])) target="_blank" rel="noopener" @endif
                                   class="concierge-row group relative isolate flex items-center gap-4 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] p-4 sm:p-5">
                                    <span class="concierge-icon flex size-12 shrink-0 items-center justify-center rounded-xl">
                                        <x-site.icon :name="$channel['icon']" class="size-5" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="concierge-label block text-[12px] font-semibold tracking-[0.14em] uppercase">{{ $channel['label'] }}</span>
                                        <span class="concierge-value mt-0.5 block font-semibold break-words" @if (! empty($channel['ltr'])) dir="ltr" @endif>{{ $channel['value'] }}</span>
                                    </span>
                                    <x-site.icon name="arrow-right" class="concierge-arrow size-5 shrink-0 rtl:rotate-180" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Live local time in Dhaka, for visitors calling from abroad. --}}
                <div class="mt-auto flex flex-wrap items-center gap-x-5 gap-y-2 pt-9 text-[14px] text-white/70" data-reveal style="--reveal-delay: 200ms"
                     x-data="dhakaClock(@js($locale === 'bn' ? 'bn-BD' : 'en-GB'))">
                    <span class="flex items-center gap-2.5">
                        <span class="relative flex size-2.5" aria-hidden="true"><span class="concierge-ping absolute inset-0 rounded-full bg-emerald"></span><span class="relative size-2.5 rounded-full bg-emerald"></span></span>
                        <span>{{ __('Dhaka time') }}</span>
                        <time class="font-display text-[1.25rem] font-semibold text-white tabular-nums" x-text="time" :datetime="iso"></time>
                    </span>
                    <span class="text-white/55" x-text="day"></span>
                </div>
            </div>

            <div class="p-7 sm:p-10 lg:p-12" x-data="{ topic: null }">
                <div data-reveal>
                    <p class="font-display text-display-sm text-brand">{{ __('Send us a message') }}</p>
                    <p class="mt-1.5 text-[15px] text-ink-2">{{ __('Leave your details and our team will call you, In Sha Allah.') }}</p>
                </div>

                {{-- Topic chips fill in the subject. --}}
                <div class="mt-6" data-reveal style="--reveal-delay: 80ms">
                    <p class="mb-2.5 text-[14px] font-semibold text-ink" id="topic-label-{{ $section->id }}">{{ __('What is it about?') }}</p>
                    <div class="flex flex-wrap gap-2" role="group" aria-labelledby="topic-label-{{ $section->id }}">
                        @foreach ($topics as $topic)
                            <button type="button" class="concierge-chip rounded-full px-4 py-2 text-[14px] font-semibold"
                                    :class="topic === @js($topic) && 'is-active'" :aria-pressed="(topic === @js($topic)).toString()"
                                    @click="topic = @js($topic); const f = document.getElementById('f-{{ $section->id }}-subject'); if (f) f.value = topic">{{ $topic }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-7" data-reveal style="--reveal-delay: 140ms">
                    <x-site.form-shell :section="$section" :submit-label="$c['submit_label'] ?: __('Send message')" :success="$c['success'] ?: __('Thank you! We will contact you shortly.')" plain>
                        @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'name', 'label' => __('Full name'), 'type' => 'text', 'required' => true, 'autocomplete' => 'name'])
                        @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'phone', 'label' => __('Mobile number'), 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel'])
                        @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => false, 'autocomplete' => 'email'])
                        @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'subject', 'label' => __('Subject'), 'type' => 'text', 'required' => false])
                        @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'message', 'label' => __('Message'), 'type' => 'textarea', 'required' => true])
                    </x-site.form-shell>
                </div>
            </div>
        </div>

        @if ($map)
            <div class="mx-auto mt-8 max-w-6xl overflow-hidden rounded-[2rem] shadow-[var(--shadow-soft)] ring-1 ring-line" data-reveal>
                <iframe src="{{ $map }}" title="{{ __('Office location on Google Maps') }}" class="h-[360px] w-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
        @endif
    </x-site.block>
@else
<x-site.block :style="$style" :section="$section">
    <div class="grid items-start gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">
        <x-site.block-heading :c="$c" align="left" class="lg:sticky lg:top-28" :as="$first ? 'h1' : 'h2'" />

        <x-site.form-shell :section="$section" :submit-label="$c['submit_label'] ?: __('Send message')" :success="$c['success'] ?: __('Thank you! We will contact you shortly.')">
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'name', 'label' => __('Full name'), 'type' => 'text', 'required' => true, 'autocomplete' => 'name'])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'phone', 'label' => __('Mobile number'), 'type' => 'tel', 'required' => true, 'autocomplete' => 'tel'])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => false, 'autocomplete' => 'email'])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'subject', 'label' => __('Subject'), 'type' => 'text', 'required' => false])
            @include('sections.partials.form-field', ['sectionId' => $section->id, 'name' => 'message', 'label' => __('Message'), 'type' => 'textarea', 'required' => true])
        </x-site.form-shell>
    </div>
</x-site.block>
@endif
