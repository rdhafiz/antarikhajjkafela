{{-- Local-only reference of the public design system. --}}
@php
    seo()->title('Design system')->noindex()
        ->breadcrumb(__('Home'), url('/'))
        ->breadcrumb('Design system');
@endphp
<x-site.layout>
    <x-site.page-header
        eyebrow="Design system"
        title="Components & tokens"
        intro="A living reference of the typography, colors and building blocks used across the website."
    />

    <x-site.section tone="white" spacing="md">
        <div class="grid gap-12 lg:grid-cols-2">
            <div>
                <p class="eyebrow">Typography</p>
                <p class="mt-6 font-display text-display-xl text-brand">Display XL</p>
                <p class="mt-3 font-display text-display-lg text-brand">Display large</p>
                <p class="mt-3 font-display text-display-md text-brand">Display medium</p>
                <p class="mt-3 font-display text-display-sm text-brand">Display small</p>
                <p class="mt-6 max-w-prose text-ink-2">Body text is set in Plus Jakarta Sans for English and Hind Siliguri for Bangla, with generous line height for comfortable reading on phones.</p>
                <div lang="bn" class="mt-8 rounded-2xl bg-sand-100 p-6">
                    <p class="font-display text-display-md text-brand">নিরাপদ ও আরামদায়ক হজ যাত্রা</p>
                    <p class="mt-3 text-ink-2">অভিজ্ঞ আলেমদের তত্ত্বাবধানে হজ ও উমরাহ পালনের জন্য আমরা আছি আপনার পাশে। ভিসা, টিকেট, হোটেল ও গাইডেন্স—সবকিছু এক জায়গায়।</p>
                </div>
            </div>
            <div>
                <p class="eyebrow">Colors</p>
                <div class="mt-6 grid grid-cols-3 gap-3 text-[13px]">
                    @foreach ([['bg-brand', 'Primary'], ['bg-gold', 'Secondary'], ['bg-emerald', 'Accent'], ['bg-brand-950', 'Navy deep'], ['bg-sand-100', 'Sand'], ['bg-white ring-1 ring-line', 'White']] as [$class, $label])
                        <div>
                            <div class="h-20 rounded-2xl {{ $class }}"></div>
                            <p class="mt-2 font-semibold text-ink">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                <p class="eyebrow mt-12">Buttons</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <x-site.button href="#" icon-right="arrow-right">Primary</x-site.button>
                    <x-site.button href="#" variant="gold" icon="phone">Call us</x-site.button>
                    <x-site.button href="#" variant="outline">Outline</x-site.button>
                    <x-site.button href="#" variant="whatsapp" icon="whatsapp">WhatsApp</x-site.button>
                    <x-site.button href="#" size="sm">Small</x-site.button>
                    <x-site.button href="#" variant="gold" size="lg">Large</x-site.button>
                </div>
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="navy" pattern spacing="md">
        <x-site.section-heading light eyebrow="Section heading" title="On a dark background" intro="Headings, eyebrows and buttons adapt to dark sections." />
        <div class="mt-10 flex justify-center gap-3">
            <x-site.button href="#" variant="gold">Primary action</x-site.button>
            <x-site.button href="#" variant="outline-light">Secondary</x-site.button>
        </div>
    </x-site.section>

    <x-site.section tone="sand" spacing="md">
        <div class="grid gap-10 lg:grid-cols-[1fr_1.2fr]">
            <x-site.section-heading align="left" eyebrow="Cards" title="Content cards" intro="Rounded, softly shadowed surfaces on the warm sand background." />
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach (['plane' => 'Air ticketing', 'building' => 'Hotel booking', 'users' => 'Group Umrah', 'calendar' => 'Hajj pre-registration'] as $icon => $title)
                    <div class="card-site p-6">
                        <x-site.icon :name="$icon" class="size-7 text-gold-deep" />
                        <p class="mt-4 font-display text-xl text-brand">{{ $title }}</p>
                        <p class="mt-2 text-[15px] text-ink-2">Short supporting description for this service.</p>
                    </div>
                @endforeach
            </div>
        </div>
    </x-site.section>

    <x-site.section tone="white" spacing="md">
        <div class="mx-auto max-w-3xl">
            <p class="eyebrow">Rich text</p>
            <div class="prose-site mt-6">
                <h2>Steps to perform Umrah</h2>
                <p>Make the sincere intention to perform Umrah <strong>solely for the sake of Allah</strong> before reaching the Miqat, then enter the state of Ihram. <a href="#">Read the full guide</a>.</p>
                <ul>
                    <li>Niyyah (intention) and Talbiyah</li>
                    <li>Tawaf around the Kaaba, seven rounds</li>
                    <li>Sa'i between Safa and Marwah</li>
                </ul>
                <blockquote>From one Umrah to another is an expiation for the sins committed between them.</blockquote>
                <table>
                    <thead><tr><th>Package</th><th>Duration</th><th>From</th></tr></thead>
                    <tbody>
                        <tr><td>Umrah Standard</td><td>7 days</td><td>৳1,60,000</td></tr>
                        <tr><td>Umrah Regular</td><td>14 days</td><td>৳1,45,000</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </x-site.section>
</x-site.layout>
