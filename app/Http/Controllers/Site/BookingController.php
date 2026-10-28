<?php

namespace App\Http\Controllers\Site;

use App\Domain\Booking\BookingLog;
use App\Domain\Booking\BookingService;
use App\Domain\Booking\TravelDates;
use App\Domain\Forms\Recaptcha;
use App\Domain\Payments\GatewayException;
use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\OnlinePayments;
use App\Domain\Travel\PackagePresenter;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Rules\PlainText;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * The public booking flow: form → booking → optional online advance →
 * the customer's private booking page. Customers have no accounts; their
 * booking page is a signed link, also reachable through the status lookup.
 */
class BookingController extends Controller
{
    public const MAX_TRAVELERS = 50;

    public const MAX_UPLOAD_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private readonly BookingService $bookings,
        private readonly OnlinePayments $payments,
    ) {}

    public function create(Request $request, string $slug): Response|RedirectResponse
    {
        $package = $this->bookablePackage($slug);
        $locale = app()->getLocale();
        $name = $package->getTranslation('name', $locale);

        seo()->title(__('Book :package', ['package' => $name]))
            ->noindex()
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb($name, $package->url())
            ->breadcrumb(__('Booking'), route('bookings.create', $package->slug));

        $options = $package->types()->with('prices')->get()->flatMap(fn ($type) => $type->prices->map(fn (PackagePrice $price) => [
            'id' => $price->id,
            'type' => $type->getTranslation('name', $locale),
            'title' => $price->getTranslation('title', $locale),
            'price' => $package->show_price ? (float) $price->price : null,
            'unit' => $price->unit,
            'unit_label' => PackagePresenter::unitLabel($price->unit),
        ]))->values();

        abort_if($options->isEmpty(), 404);

        $selected = $options->firstWhere('id', (int) $request->query('option', old('option'))) ?? $options->first();

        return response()->view('site.bookings.create', [
            'package' => $package,
            'name' => $name,
            'options' => $options,
            'selected' => $selected,
            'travel' => app(TravelDates::class)->form($package->service),
            'showPrice' => $package->show_price,
            // Packages that hide their price are booked first and priced by phone.
            'gateway' => $package->show_price ? app(GatewayManager::class)->active() : null,
        ]);
    }

    /**
     * The form posts in the background (JSON): a problem comes back as field
     * errors with the visitor's files still chosen; success returns where to
     * go next. Without JavaScript it is an ordinary post and redirect.
     */
    public function store(Request $request, string $slug, Recaptcha $recaptcha): RedirectResponse|JsonResponse
    {
        $package = $this->bookablePackage($slug);

        // Bots fill the hidden honeypot field.
        abort_if(filled($request->input('website')), 422);

        // Browsers count a line break as one character but send two (CRLF): count it the way the box did.
        if (is_string($request->input('note'))) {
            $request->merge(['note' => str_replace("\r\n", "\n", $request->input('note'))]);
        }

        $data = $request->validate([
            'option' => ['required', 'integer', Rule::exists('package_prices', 'id')->whereNull('deleted_at')->whereIn('package_type_id', $package->types()->pluck('id'))],
            'travelers' => ['required', 'integer', 'min:1', 'max:'.self::MAX_TRAVELERS],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_TRAVELERS],
            'name' => ['required', 'string', 'max:120', new PlainText],
            // A real mobile number: Bangladeshi (01XXXXXXXXX, with or without +88) or international with its country code.
            'phone' => ['required', 'string', 'max:25', (new Phone)->international()->country('BD')->type('mobile')],
            // Required: the confirmation and every later update are emailed (there are no customer accounts).
            'email' => ['required', 'email:rfc,filter', 'max:190'],
            'address' => ['nullable', 'string', 'max:300'],
            'note' => ['nullable', 'string', 'max:2000'],
            'passport' => ['nullable', 'array', 'max:10'],
            'passport.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'nid' => ['nullable', 'array', 'max:10'],
            'nid.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'payment' => ['required', Rule::in($package->show_price ? ['now', 'later'] : ['later'])],
            'agree' => ['accepted'],
            ...app(TravelDates::class)->rules(app(TravelDates::class)->mode($package->service), $request->integer('travel_year') ?: null),
        ], [
            'email.required' => __('Enter your email; we send your booking confirmation and updates there.'),
            'email.email' => __('Enter a valid email address, e.g. name@example.com.'),
            'phone.phone' => __('Enter a valid mobile number, e.g. 01712-345678, or an international number starting with +.'),
            'agree.accepted' => __('Please accept the booking terms.'),
            // Laravel words file sizes in kilobytes; staff and pilgrims think in MB.
            'passport.*.max' => __(':attribute must be 5 MB or smaller.'),
            'nid.*.max' => __(':attribute must be 5 MB or smaller.'),
            'passport.max' => __('Upload up to 10 passport copies.'),
            'nid.max' => __('Upload up to 10 NID copies.'),
            'travelers.required' => __('Enter the number of travelers.'),
            'travelers.min' => __('Book 1 to :max travelers at a time. For a larger group, please call us.', ['max' => Money::digits(self::MAX_TRAVELERS)]),
            'travelers.max' => __('Book 1 to :max travelers at a time. For a larger group, please call us.', ['max' => Money::digits(self::MAX_TRAVELERS)]),
            'rooms.min' => __('Book 1 to :max rooms at a time. For a larger group, please call us.', ['max' => Money::digits(self::MAX_TRAVELERS)]),
            'rooms.max' => __('Book 1 to :max rooms at a time. For a larger group, please call us.', ['max' => Money::digits(self::MAX_TRAVELERS)]),
            'note.max' => __('Please keep this to :max characters.'),
        ], [
            'option' => __('Package option'),
            'travelers' => __('Number of travelers'),
            'travel_month' => __('Travel month'),
            'travel_year' => $package->service?->travel_date === 'hajj_year' ? __('Hajj year') : __('Travel year'),
            'rooms' => __('Number of rooms'),
            'name' => __('Full name'),
            'phone' => __('Mobile number'),
            'email' => __('Email'),
            'address' => __('Address'),
            'note' => __('Message'),
            'passport' => __('Passport copies'),
            'passport.*' => __('Passport copy'),
            'nid' => __('NID copies'),
            'nid.*' => __('NID copy'),
        ]);

        $files = [...($data['passport'] ?? []), ...($data['nid'] ?? [])];

        if (count($files) > 10) {
            throw ValidationException::withMessages(['passport' => __('Upload up to 10 documents in total.')]);
        }

        if (array_sum(array_map(fn ($file) => $file->getSize(), $files)) > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages(['passport' => __('The documents are too large together. Upload up to 15 MB in total.')]);
        }

        if (! $recaptcha->passes($request->input('recaptcha_token'), $request->ip())) {
            throw ValidationException::withMessages(['recaptcha' => __('We could not verify your submission. Please try again.')]);
        }

        $documents = [
            ...array_map(fn ($file) => ['kind' => 'passport', 'file' => $file], $data['passport'] ?? []),
            ...array_map(fn ($file) => ['kind' => 'nid', 'file' => $file], $data['nid'] ?? []),
        ];

        $booking = $this->bookings->create($package, PackagePrice::findOrFail($data['option']), $data, $documents, $request->ip());

        $target = $data['payment'] === 'now' && $this->payments->canPay($booking)
            ? $this->gatewayUrl($booking)
            : $booking->publicUrl();

        if ($target === $booking->publicUrl()) {
            session()->flash('booking_created', true);
        }

        return $request->expectsJson()
            ? response()->json(['redirect' => $target])
            : redirect()->to($target);
    }

    /**
     * The customer's booking page (signed link from the email or the lookup).
     */
    public function show(Booking $booking): Response
    {
        $booking->load(['payments' => fn ($q) => $q->where('status', 'paid'), 'updates']);

        // Logged at most every half hour, and not when staff open the customer link while signed in.
        $log = app(BookingLog::class);
        if (! auth()->check() && ! $log->recently($booking, 'customer_viewed', 30)) {
            $log->record($booking, 'customer_viewed', ['ip' => request()->ip()]);
        }

        seo()->title(__('Booking :reference', ['reference' => $booking->reference]))->noindex();

        // The signed link is the customer's key: never pass it on in a Referer.
        return response()->view('site.bookings.show', [
            'booking' => $booking,
            'canPay' => $this->payments->canPay($booking),
            'balanceDue' => $this->payments->canPayBalance($booking) ? $this->payments->balanceDue($booking) : 0.0,
        ])->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Pay the outstanding advance online from the booking page.
     */
    public function pay(Request $request, Booking $booking): RedirectResponse
    {
        // A page left open after staff recorded the payment: back to the booking, which shows what is due now.
        if (! $this->payments->canPay($booking) && ! $this->payments->canPayBalance($booking)) {
            return redirect()->to($booking->publicUrl());
        }

        // Paying the balance: any part of it, from the minimum up to what is left.
        $amount = null;
        if (! $this->payments->canPay($booking)) {
            $due = $this->payments->balanceDue($booking);
            $amount = (float) $request->validate([
                'amount' => ['required', 'numeric', 'min:'.OnlinePayments::MINIMUM, 'max:'.$due],
            ], [
                'amount.max' => __('You can pay up to :amount.', ['amount' => Money::format($due)]),
                'amount.min' => __('Pay at least :amount online.', ['amount' => Money::format(OnlinePayments::MINIMUM)]),
            ], ['amount' => __('Amount')])['amount'];
        }

        return $this->toGateway($booking, $amount);
    }

    public function status(): Response
    {
        seo()->title(__('Find your booking'))
            ->description(__('Find your booking with its reference number and your phone number or email.'))
            ->breadcrumb(__('Home'), url('/'))
            ->breadcrumb(__('Find your booking'), route('bookings.status'));

        return response()->view('site.bookings.status');
    }

    /**
     * Find a booking by reference plus the phone number or email used.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:24'],
            'contact' => ['required', 'string', 'max:190'],
        ], [], ['reference' => __('Booking reference'), 'contact' => __('Phone number or email')]);

        $booking = Booking::where('reference', strtoupper(trim($data['reference'])))->first();
        $contact = trim($data['contact']);
        $digits = fn (?string $phone) => substr(preg_replace('/\D/', '', (string) $phone), -10);

        $matches = $booking && (
            (str_contains($contact, '@') && strcasecmp((string) $booking->customer_email, $contact) === 0)
            || (! str_contains($contact, '@') && strlen($digits($contact)) >= 7 && $digits($contact) === $digits($booking->customer_phone))
        );

        if (! $matches) {
            return back()->withInput()->withErrors(['reference' => __('We could not find a booking with these details. Please check them or call us.')]);
        }

        app(BookingLog::class)->record($booking, 'customer_lookup', ['ip' => $request->ip()]);

        return redirect()->to($booking->publicUrl());
    }

    private function toGateway(Booking $booking, ?float $amount = null): RedirectResponse
    {
        return redirect()->away($this->gatewayUrl($booking, $amount));
    }

    /**
     * The payment page, or the booking page saying online payment is not available right now.
     */
    private function gatewayUrl(Booking $booking, ?float $amount = null): string
    {
        try {
            return $this->payments->start($booking, $amount);
        } catch (GatewayException) {
            session()->flash('booking_created', true);
            session()->flash('payment_result', 'unavailable');

            return $booking->publicUrl();
        }
    }

    private function bookablePackage(string $slug): Package
    {
        $package = Package::published()->where('slug', $slug)->firstOrFail();

        abort_unless($package->bookingState() === 'open', 404);

        return $package;
    }
}
