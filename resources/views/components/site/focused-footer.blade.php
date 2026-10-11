@inject('site', App\Support\SiteContext::class)
<footer class="bg-brand-950 text-[13px] text-white/55">
    <div class="container-site flex flex-col gap-3 py-8 sm:flex-row sm:items-center sm:justify-between">
        <p>© {{ now()->year }} {{ $site->name() }}. {{ __('All rights reserved.') }}</p>
        <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
            @foreach ($site->phones() as $phone)
                <a href="{{ $phone['href'] }}" class="hover:text-white">{{ $phone['display'] }}</a>
            @endforeach
            <a href="{{ url('/') }}" class="hover:text-white">{{ __('Visit our website') }}</a>
        </div>
    </div>
</footer>
