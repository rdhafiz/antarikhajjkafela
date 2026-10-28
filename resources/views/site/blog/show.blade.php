@php
    use App\Domain\Blog\PostPresenter;
    use App\Support\Money;

    $locale = app()->getLocale();
    $shareUrl = urlencode($post->url());
    $shareText = urlencode($p['title']);
@endphp
<x-site.layout header="overlay">
    @if ($preview)
        <x-site.preview-bar
            :state="$post->state()"
            :label="match ($post->state()) { 'scheduled' => __('Scheduled for :date', ['date' => $p['date']]), 'draft' => __('Draft, not visible to visitors'), default => __('Published') }"
            :edit-url="admin_url('blog/'.$post->id)"
        />
    @endif

    <article>
        {{-- Header: the shared hero over the post's banner --}}
        <x-site.hero
            tag="header"
            :eyebrow="$post->category?->getTranslation('name', $locale)"
            :title="$p['title']"
            :intro="$p['excerpt']"
            :image="$post->banner"
        >
            <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-3 text-[14px] text-white/75">
                <span class="flex items-center gap-2.5">
                    <img src="{{ PostPresenter::authorImage($post) }}" alt="" width="36" height="36" class="size-9 rounded-full bg-white object-cover ring-2 ring-gold/50">
                    <span class="font-semibold text-white">{{ $p['author'] }}</span>
                </span>
                @if ($p['date'])
                    <span class="flex items-center gap-1.5"><x-site.icon name="calendar" class="size-4 text-gold" /><time datetime="{{ $post->published_at->toDateString() }}">{{ $p['date'] }}</time></span>
                @endif
                <span class="flex items-center gap-1.5"><x-site.icon name="clock" class="size-4 text-gold" />{{ trans_choice(':count min read|:count min read', $p['minutes'], ['count' => Money::digits($p['minutes'])]) }}</span>
            </div>
        </x-site.hero>

        {{-- Body --}}
        <div class="section-light relative isolate overflow-hidden bg-white">
            <x-site.decor />
            <div class="container-site py-14 sm:py-20">
                <div class="mx-auto max-w-[760px] text-[1.0625rem]">
                    @php
                        // A Hajj or Umrah post invites the reader to book, halfway through.
                        $half = $p['cta'] ? (int) ceil(count($p['blocks']) / 2) : count($p['blocks']);
                    @endphp
                    <div data-reveal-children>
                        @include('partials.content-blocks', ['blocks' => array_slice($p['blocks'], 0, $half)])
                    </div>
                    @if ($p['cta'])
                        @include('partials.booking-cta', ['cta' => $p['cta']])
                        @if (count($p['blocks']) > $half)
                            <div class="mt-6" data-reveal-children>
                                @include('partials.content-blocks', ['blocks' => array_slice($p['blocks'], $half)])
                            </div>
                        @endif
                    @endif

                    <footer class="mt-14 space-y-6 border-t border-line pt-8" data-reveal>
                        @if ($post->tags)
                            <div class="flex flex-wrap items-center gap-2">
                                <x-site.icon name="tag" class="size-4.5 text-gold-deep" />
                                <span class="sr-only">{{ __('Tags') }}:</span>
                                @foreach ($post->tags as $tag)
                                    <a href="{{ $blogUrl }}?tag={{ urlencode($tag) }}" class="rounded-full bg-sand-100 px-3 py-1 text-[13px] font-semibold text-ink-2 transition-colors hover:bg-brand hover:text-white">#{{ $tag }}</a>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-3" x-data="{ copied: false }">
                            <span class="text-[14px] font-semibold text-ink">{{ __('Share') }}</span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener" class="flex size-10 items-center justify-center rounded-full bg-[#1877f2] text-white transition-transform hover:-translate-y-0.5" aria-label="{{ __('Share on Facebook') }}"><x-site.icon name="facebook" class="size-5" /></a>
                            <a href="https://wa.me/?text={{ $shareText }}%20{{ $shareUrl }}" target="_blank" rel="noopener" class="flex size-10 items-center justify-center rounded-full bg-[#25d366] text-white transition-transform hover:-translate-y-0.5" aria-label="{{ __('Share on WhatsApp') }}"><x-site.icon name="whatsapp" class="size-5" /></a>
                            <button type="button" class="flex h-10 items-center gap-2 rounded-full bg-sand-100 px-4 text-[14px] font-semibold text-ink-2 transition-colors hover:bg-brand hover:text-white"
                                    @click="navigator.clipboard?.writeText(@js($post->url())).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                                <x-site.icon name="link" class="size-4" />
                                <span x-text="copied ? @js(__('Link copied')) : @js(__('Copy link'))">{{ __('Copy link') }}</span>
                            </button>
                            <span class="sr-only" aria-live="polite" x-text="copied ? @js(__('Link copied')) : ''"></span>
                        </div>
                    </footer>
                </div>
            </div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="section-light relative isolate overflow-hidden bg-sky-soft py-16 sm:py-24" aria-labelledby="related-title">
            <x-site.decor />
            <div class="container-site">
                <div class="mb-10 flex flex-wrap items-end justify-between gap-4" data-reveal>
                    <div>
                        <p class="eyebrow">{{ __('Keep reading') }}</p>
                        <h2 id="related-title" class="sec-heading mt-3 text-display-lg">{{ __('Related articles') }}</h2>
                    </div>
                    <a href="{{ $blogUrl }}" class="inline-flex items-center gap-1.5 font-semibold text-brand hover:underline">{{ __('All articles') }} <x-site.icon name="arrow-right" class="size-4 rtl:rotate-180" /></a>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $index => $item)
                        <x-site.post-card :post="$item" data-reveal :style="'--reveal-delay: '.(($index % 3) * 80).'ms'" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-site.layout>
