@inject('site', App\Support\SiteContext::class)
@if ($url = $site->whatsappUrl(__('Assalamu alaikum, I would like to know about your travel services.')))
<aside aria-label="{{ __('Chat with us on WhatsApp') }}" x-data="floatingContact">
    <a
        href="{{ $url }}"
        target="_blank"
        rel="noopener"
        class="group fixed right-4 bottom-4 z-40 flex items-center rounded-full bg-[#15803d] p-3.5 text-white shadow-[0_12px_30px_-8px_rgb(21_128_61/0.6)] transition-[translate,box-shadow,opacity] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-0.5 sm:right-6 sm:bottom-6"
        style="margin-bottom: env(safe-area-inset-bottom)"
        aria-label="{{ __('Chat with us on WhatsApp') }}"
        :class="tucked && 'translate-y-[calc(100%+2rem)] opacity-0 pointer-events-none'"
        @focus="tucked = false"
    >
        <span class="absolute inset-0 animate-[wa-ping_2.4s_ease-out_infinite] rounded-full bg-[#15803d] motion-reduce:hidden" aria-hidden="true"></span>
        <x-site.icon name="whatsapp" class="relative size-7" />
        <span class="relative hidden max-w-0 overflow-hidden text-[15px] font-semibold whitespace-nowrap transition-[max-width,padding] duration-300 group-hover:max-w-40 group-hover:pr-1.5 group-hover:pl-2 group-focus-visible:max-w-40 group-focus-visible:pr-1.5 group-focus-visible:pl-2 sm:block">
            {{ __('Chat with us') }}
        </span>
    </a>
    <style>
        @keyframes wa-ping {
            0% { transform: scale(1); opacity: 0.45; }
            70%, 100% { transform: scale(1.45); opacity: 0; }
        }
    </style>
</aside>
@endif
