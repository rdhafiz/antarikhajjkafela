<x-site.layout>
    <section class="section-light bg-sand-50 py-12 sm:py-16">
        <div class="container-site max-w-xl">
            {{-- No hero here: a compact title so the form is in view right away. --}}
            <div class="mb-8 text-center" data-reveal>
                <h1 class="font-display text-display-md text-brand">{{ __('Check booking status') }}</h1>
                <p class="mt-3 text-[15.5px] leading-relaxed text-ink-2">{{ __('Enter your booking reference and the phone number or email you booked with.') }}</p>
            </div>

            <form method="POST" action="{{ route('bookings.lookup') }}" class="card-site space-y-5 p-6 sm:p-9" novalidate data-reveal style="--reveal-delay: 100ms">
                @csrf
                @error('reference')
                    <p id="lookup-error" class="rounded-xl bg-red-50 px-4 py-3 text-[14px] font-medium text-red-800" role="alert">{{ $message }}</p>
                @enderror
                <div>
                    <label for="reference" class="label-site">{{ __('Booking reference') }}<span class="text-red-600">*</span></label>
                    <input id="reference" name="reference" value="{{ old('reference') }}" required autocomplete="off" placeholder="AHK-2026-000123" class="input-site uppercase placeholder:normal-case" @error('reference') aria-invalid="true" aria-describedby="lookup-error" @enderror>
                </div>
                <div>
                    <label for="contact" class="label-site">{{ __('Phone number or email') }}<span class="text-red-600">*</span></label>
                    <input id="contact" name="contact" value="{{ old('contact') }}" required autocomplete="tel" class="input-site">
                    @error('contact')<p class="error-site">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-full">{{ __('Find my booking') }}</button>
            </form>
        </div>
    </section>
</x-site.layout>
