@inject('site', App\Support\SiteContext::class)
@php
    use App\Domain\Travel\PackagePresenter;
    use App\Support\Money;

    $result = session('payment_result');
    $statusLabels = [
        'pending' => __('Pending confirmation'),
        'confirmed' => __('Confirmed'),
        'advance_paid' => __('Advance paid'),
        'fully_paid' => __('Fully paid'),
        'completed' => __('Completed'),
        'cancelled' => __('Cancelled'),
    ];
    $tone = match ($booking->status) {
        'cancelled' => 'bg-red-600 text-white',
        'pending' => 'bg-gold text-brand-950',
        default => 'bg-emerald text-white',
    };
    $phones = $site->phones();
@endphp
<x-site.layout :analytics="false">
    <section class="section-light bg-sand-50 py-12 sm:py-16">
        <div class="container-site max-w-4xl">
            {{-- Outcome messages --}}
            @if ($result === 'paid')
                <div class="mb-6 flex gap-4 rounded-2xl bg-emerald/10 p-5 text-emerald ring-1 ring-emerald/25" role="status">
                    <x-site.icon name="check-circle" class="size-7 shrink-0" />
                    <div><p class="font-semibold text-ink">{{ __('Payment received. Jazakallahu khairan!') }}</p><p class="mt-1 text-[14px] text-ink-2">{{ __('A receipt has been sent to your email. Our team will contact you with the next steps.') }}</p></div>
                </div>
            @elseif (in_array($result, ['failed', 'cancelled'], true))
                <div class="mb-6 rounded-2xl bg-red-50 p-5 ring-1 ring-red-200" role="alert">
                    <p class="font-semibold text-red-800">{{ $result === 'cancelled' ? __('The payment was cancelled.') : __('The payment did not go through.') }}</p>
                    <p class="mt-1 text-[14px] text-red-800/80">{{ __('Your booking is saved. You can try again below or pay us directly.') }}</p>
                </div>
            @elseif ($result === 'pending')
                <div class="mb-6 rounded-2xl bg-gold/15 p-5 ring-1 ring-gold/40" role="status">
                    <p class="font-semibold text-ink">{{ __('We are confirming your payment.') }}</p>
                    <p class="mt-1 text-[14px] text-ink-2">{{ __('This can take a few minutes. Refresh this page later, or call us if the amount was taken from your account.') }}</p>
                </div>
            @elseif ($result === 'unavailable')
                <div class="mb-6 rounded-2xl bg-gold/15 p-5 ring-1 ring-gold/40" role="status">
                    <p class="font-semibold text-ink">{{ __('Online payment is not available right now.') }}</p>
                    <p class="mt-1 text-[14px] text-ink-2">{{ __('Your booking is saved. Please try again later or contact us to pay.') }}</p>
                </div>
            @elseif (session('booking_created'))
                <div class="mb-6 flex gap-4 rounded-2xl bg-emerald/10 p-5 text-emerald ring-1 ring-emerald/25" role="status">
                    <x-site.icon name="check-circle" class="size-7 shrink-0" />
                    <div><p class="font-semibold text-ink">{{ __('Your booking has been received.') }}</p><p class="mt-1 text-[14px] text-ink-2">{{ __('Our team will call you shortly to confirm. Save this page or your reference number.') }}</p></div>
                </div>
            @endif

            <div class="overflow-hidden rounded-[1.75rem] bg-white shadow-[var(--shadow-lift)] ring-1 ring-line">
                {{-- Header --}}
                <div class="relative bg-brand-950 p-6 text-white sm:p-9">
                    <div class="pattern-star absolute inset-0 opacity-[0.05]" aria-hidden="true"></div>
                    <div class="relative flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <p class="text-[12px] font-semibold tracking-wider text-gold uppercase">{{ __('Booking reference') }}</p>
                            <h1 class="mt-2 font-display text-[2rem] leading-none font-semibold tracking-wide">{{ $booking->reference }}</h1>
                            <p class="mt-3 text-[14px] text-white/65">{{ __('Booked on :date', ['date' => $booking->created_at->copy()->locale(app()->getLocale())->translatedFormat('j F Y')]) }}</p>
                        </div>
                        <span class="rounded-full px-4 py-1.5 text-[14px] font-semibold {{ $tone }}">{{ $statusLabels[$booking->status] ?? $booking->status }}</span>
                    </div>
                </div>

                <div class="grid gap-8 p-6 sm:p-9 md:grid-cols-[1.2fr_1fr]">
                    {{-- What was booked --}}
                    <div>
                        <h2 class="text-[13px] font-semibold tracking-wider text-ink-3 uppercase">{{ __('Package') }}</h2>
                        <p class="mt-2 font-display text-display-sm text-brand">
                            @if ($booking->package?->isPublished())
                                <a href="{{ $booking->package->url() }}" class="hover:underline">{{ $booking->snap('package') }}</a>
                            @else
                                {{ $booking->snap('package') }}
                            @endif
                        </p>
                        <p class="mt-1 text-ink-2">{{ trim($booking->snap('type').' – '.$booking->snap('option'), ' –') }}</p>

                        <dl class="mt-6 grid grid-cols-2 gap-4 text-[15px]">
                            <div><dt class="text-[13px] text-ink-3">{{ __('Travelers') }}</dt><dd class="font-semibold text-ink">{{ Money::digits($booking->travelers) }}</dd></div>
                            <div><dt class="text-[13px] text-ink-3">{{ __('Price') }}</dt><dd class="font-semibold text-ink">{{ Money::html($booking->unit_price) }} <span class="text-[13px] font-normal text-ink-3">{{ PackagePresenter::unitLabel($booking->snapshot['unit'] ?? 'person') }}</span></dd></div>
                            <div><dt class="text-[13px] text-ink-3">{{ __('Name') }}</dt><dd class="font-semibold text-ink">{{ $booking->customer_name }}</dd></div>
                            <div><dt class="text-[13px] text-ink-3">{{ __('Mobile number') }}</dt><dd class="font-semibold text-ink">{{ $booking->customer_phone }}</dd></div>
                        </dl>
                    </div>

                    {{-- Amounts --}}
                    <div class="rounded-2xl bg-sand-50 p-6 ring-1 ring-line">
                        <dl class="space-y-3 text-[15px]">
                            <div class="flex justify-between gap-4"><dt class="text-ink-2">{{ __('Total') }}</dt><dd class="font-semibold text-ink">{{ Money::html($booking->total) }}</dd></div>
                            @if ((float) $booking->advance_amount > 0)
                                <div class="flex justify-between gap-4"><dt class="text-ink-2">{{ __('Advance') }}</dt><dd class="text-ink">{{ Money::html($booking->advance_amount) }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-4"><dt class="text-ink-2">{{ __('Paid') }}</dt><dd class="font-semibold text-emerald">{{ Money::html($booking->paid_amount) }}</dd></div>
                            <div class="flex justify-between gap-4 border-t border-line pt-3 text-lg"><dt class="font-semibold text-ink">{{ __('Balance') }}</dt><dd class="font-display font-semibold text-brand">{{ Money::html($booking->balance()) }}</dd></div>
                        </dl>

                        @if ($canPay)
                            <form method="POST" action="{{ URL::signedRoute('bookings.pay', ['booking' => $booking->reference]) }}" class="mt-6" x-data="{ busy: false }" @submit="busy = true">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full" :disabled="busy">{{ __('Pay advance :amount online', ['amount' => Money::format($booking->advanceDue())]) }}</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($booking->payments->isNotEmpty())
                    <div class="border-t border-line p-6 sm:px-9">
                        <h2 class="text-[13px] font-semibold tracking-wider text-ink-3 uppercase">{{ __('Payments') }}</h2>
                        <ul class="mt-3 divide-y divide-line">
                            @foreach ($booking->payments as $payment)
                                <li class="flex flex-wrap justify-between gap-3 py-3 text-[15px]">
                                    <span class="text-ink-2">{{ $payment->paid_at?->copy()->locale(app()->getLocale())->translatedFormat('j F Y') }} · {{ $payment->is_online ? $payment->methodLabel() : __('Office payment') }}</span>
                                    <span class="font-semibold text-ink">{{ Money::html($payment->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex flex-col gap-4 border-t border-line bg-sand-50/60 p-6 sm:flex-row sm:items-center sm:justify-between sm:px-9">
                    <p class="text-[14px] text-ink-2">{{ __('Questions about your booking? Call us with your reference number.') }}</p>
                    <div class="flex gap-3">
                        @if ($phones)
                            <x-site.button :href="$phones[0]['href']" variant="outline" icon="phone" size="sm">{{ $phones[0]['display'] }}</x-site.button>
                        @endif
                        @if ($wa = $site->whatsappUrl(__('Assalamu alaikum, my booking reference is :reference.', ['reference' => $booking->reference])))
                            <x-site.button :href="$wa" variant="whatsapp" icon="whatsapp" size="sm" external>WhatsApp</x-site.button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>
