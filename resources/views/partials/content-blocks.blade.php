{{--
    Renders localized content blocks (text, table, list, image, video).
    Expects $blocks; colors follow the surrounding section palette.
--}}
<div class="space-y-6">
    @foreach ($blocks as $block)
        @switch($block['type'])
            @case('text')
                <div class="prose-site">{!! $block['body'] !!}</div>
                @break

            @case('table')
                <figure class="overflow-x-auto rounded-2xl border border-[var(--sec-border,var(--color-line))]">
                    <table class="w-full min-w-[480px] text-left text-[15px]">
                        @foreach ($block['rows'] as $rowIndex => $row)
                            @if ($rowIndex === 0 && $block['header'])
                                <thead>
                                    <tr class="bg-brand text-white">
                                        @foreach ($row as $cell)<th scope="col" class="px-4 py-3 font-semibold">{{ $cell }}</th>@endforeach
                                    </tr>
                                </thead>
                                <tbody>
                            @else
                                @if ($rowIndex === 0)<tbody>@endif
                                <tr class="border-t border-[var(--sec-border,var(--color-line))] odd:bg-[var(--sec-surface-2,var(--color-sand-50))]">
                                    @foreach ($row as $cellIndex => $cell)
                                        <td @class(['px-4 py-3', 'sec-heading font-semibold' => $cellIndex === 0, 'sec-text' => $cellIndex > 0])>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                    @if (filled($block['caption']))<figcaption class="sec-muted border-t border-[var(--sec-border,var(--color-line))] px-4 py-2.5 text-[13px]">{{ $block['caption'] }}</figcaption>@endif
                </figure>
                @break

            @case('list')
                @if ($block['style'] === 'number')
                    <ol class="space-y-3">
                        @foreach ($block['items'] as $i => $item)
                            <li class="flex gap-3.5">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gold text-[13px] font-bold text-brand-950">{{ App\Support\Money::digits($i + 1) }}</span>
                                <span class="sec-text pt-0.5">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <ul class="grid gap-3 sm:grid-cols-2">
                        @foreach ($block['items'] as $item)
                            <li class="flex gap-3">
                                @if ($block['style'] === 'check')
                                    <span class="list-check mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-emerald text-white"><x-site.icon name="check" class="size-3.5" /></span>
                                @else
                                    <span class="mt-2.5 size-2 shrink-0 rotate-45 bg-gold" aria-hidden="true"></span>
                                @endif
                                <span class="sec-text">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @break

            @case('image')
                <figure>
                    <img
                        src="{{ $block['image']->url('md') }}"
                        srcset="{{ $block['image']->srcset() }}"
                        sizes="(min-width: 1024px) 760px, 100vw"
                        alt="{{ $block['image']->getTranslation('alt', app()->getLocale()) ?: $block['caption'] }}"
                        loading="lazy"
                        decoding="async"
                        class="w-full rounded-2xl object-cover"
                    >
                    @if (filled($block['caption']))<figcaption class="sec-muted mt-2.5 text-center text-[13px]">{{ $block['caption'] }}</figcaption>@endif
                </figure>
                @break

            @case('video')
                @php(preg_match('#(?:v=|embed/|shorts/|youtu\.be/)([\w-]{6,})#', $block['url'], $m))
                @if ($m[1] ?? null)
                    <figure x-data="{ playing: false }">
                        <div class="relative aspect-video overflow-hidden rounded-2xl bg-brand-950">
                            <template x-if="playing">
                                <iframe class="absolute inset-0 size-full" src="https://www.youtube-nocookie.com/embed/{{ $m[1] }}?autoplay=1&rel=0" title="{{ $block['caption'] ?: __('Video') }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                            </template>
                            <button type="button" x-show="!playing" @click="playing = true" class="group absolute inset-0 size-full" aria-label="{{ __('Play video') }}">
                                <img src="https://i.ytimg.com/vi/{{ $m[1] }}/hqdefault.jpg" alt="" loading="lazy" class="absolute inset-0 size-full object-cover opacity-85">
                                <span class="absolute top-1/2 left-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-gold text-brand-950 shadow-xl transition-transform group-hover:scale-110">
                                    <x-site.icon name="play" class="ml-1 size-7 fill-current" />
                                </span>
                            </button>
                        </div>
                        @if (filled($block['caption']))<figcaption class="sec-muted mt-2.5 text-center text-[13px]">{{ $block['caption'] }}</figcaption>@endif
                    </figure>
                @endif
                @break
        @endswitch
    @endforeach
</div>
