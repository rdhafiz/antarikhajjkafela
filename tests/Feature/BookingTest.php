<?php

namespace Tests\Feature;

use App\Domain\Booking\BookingCalculator;
use App\Domain\Booking\BookingService;
use App\Domain\Booking\TravelDates;
use App\Domain\Payments\Gateways\DgePayGateway;
use App\Domain\Payments\OnlinePayments;
use App\Mail\BookingPlaced;
use App\Mail\BookingReceived;
use App\Mail\BookingUpdated;
use App\Mail\PaymentReceived;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use App\Support\Money;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Admin\AdminTestCase;

class BookingTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PageSeeder::class, TravelSeeder::class]);
        Mail::fake();
    }

    private function package(): Package
    {
        return Package::where('slug', 'umrah-package')->firstOrFail();
    }

    private function price(?Package $package = null): PackagePrice
    {
        return ($package ?? $this->package())->prices()->orderBy('package_prices.id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_replace([
            'option' => $this->price()->id,
            'travelers' => 2,
            // Umrah asks for the month and year; the current month is the earliest.
            'travel_month' => now()->month,
            'travel_year' => now()->year,
            'name' => 'Abdul Karim',
            'phone' => '01711-000000',
            'email' => 'karim@example.com',
            'payment' => 'later',
            'agree' => '1',
        ], $overrides);
    }

    private function enableSslCommerz(): void
    {
        settings()->set('payment', [
            'active_gateway' => 'sslcommerz',
            'sslcommerz_mode' => 'sandbox',
            'sslcommerz_store_id' => 'teststore',
            'sslcommerz_store_password' => 'secret-pass',
        ]);
    }

    public function test_amounts_follow_the_unit_and_advance_rules(): void
    {
        $calc = app(BookingCalculator::class);
        $package = $this->package();
        $price = $this->price();

        $price->price = 100000;
        $package->advance_type = 'fixed';
        $package->advance_value = 30000;

        $price->unit = 'person';
        $this->assertSame(['total' => 300000.0, 'advance' => 90000.0], $this->pick($calc->calculate($package, $price, 3)));

        $price->unit = 'room';
        $this->assertSame(['total' => 200000.0, 'advance' => 90000.0], $this->pick($calc->calculate($package, $price, 3, 2)));

        $price->unit = 'group';
        $package->advance_type = 'percentage';
        $package->advance_value = 25;
        $this->assertSame(['total' => 100000.0, 'advance' => 25000.0], $this->pick($calc->calculate($package, $price, 8)));

        // The advance never exceeds the total.
        $package->advance_type = 'fixed';
        $package->advance_value = 150000;
        $this->assertSame(100000.0, $calc->calculate($package, $price, 1)['advance_amount']);

        $package->advance_type = 'none';
        $this->assertSame(0.0, $calc->calculate($package, $price, 1)['advance_amount']);
    }

    private function pick(array $amounts): array
    {
        return ['total' => $amounts['total'], 'advance' => $amounts['advance_amount']];
    }

    public function test_booking_without_payment(): void
    {
        $this->get('/packages/umrah-package/book')->assertOk()->assertSeeText('Book your journey');

        $response = $this->post('/packages/umrah-package/book', $this->form());

        $booking = Booking::firstOrFail();
        $response->assertRedirect($booking->publicUrl());

        $this->assertMatchesRegularExpression('/^AHK-\d{4}-\d{6}$/', $booking->reference);
        $this->assertSame('pending', $booking->status);
        $this->assertSame(round((float) $this->price()->price * 2, 2), (float) $booking->total);
        $this->assertSame('Umrah Package', $booking->snap('package', 'en'));

        Mail::assertSent(BookingReceived::class, fn ($mail) => $mail->hasTo('karim@example.com'));
        Mail::assertSent(BookingPlaced::class);

        $this->get($booking->publicUrl())->assertOk()->assertSeeText($booking->reference)->assertSeeText('Your booking has been received.');
        $this->get("/bookings/{$booking->reference}")->assertForbidden();
    }

    public function test_the_payment_step_only_shows_when_online_payment_is_possible(): void
    {
        // No gateway or no advance: every booking is "pay later", so the step and its tab are hidden.
        $this->get('/packages/umrah-package/book')->assertOk()
            ->assertDontSee('id="step-payment"', false)
            ->assertDontSeeText('Choose how you would like to pay.')
            ->assertSee('<input type="hidden" name="payment" value="later">', false)
            ->assertSeeText('booking terms and conditions');

        $this->enableSslCommerz();
        $this->package()->update(['advance_type' => 'fixed', 'advance_value' => 20000]);

        $this->get('/packages/umrah-package/book')->assertOk()
            ->assertSee('id="step-payment"', false)
            ->assertSeeText('Pay the advance now')
            ->assertSeeText('Book now, pay later');
    }

    public function test_travel_years_follow_the_season(): void
    {
        $dates = app(TravelDates::class);

        // Umrah: this year on. Hajj: Hajj 2027 registration closed in September 2026.
        $this->assertSame([2026, 2027, 2028], $dates->years('month_year', Carbon::parse('2026-10-16')));
        $this->assertSame([2027, 2028, 2029], $dates->years('hajj_year', Carbon::parse('2026-09-20')));
        $this->assertSame([2028, 2029, 2030], $dates->years('hajj_year', Carbon::parse('2026-10-16')));
        $this->assertSame([], $dates->years('none', Carbon::parse('2026-10-16')));
    }

    public function test_umrah_bookings_ask_for_the_month_and_year(): void
    {
        Carbon::setTestNow('2026-10-16 12:00:00');

        $this->get('/packages/umrah-package/book')->assertOk()->assertSeeText('Travel month')->assertSeeText('Travel year')->assertDontSeeText('Hajj year');

        // A month already past this year, or no month at all, is refused.
        $this->post('/packages/umrah-package/book', $this->form(['travel_month' => 9, 'travel_year' => 2026]))->assertSessionHasErrors('travel_month');
        $this->post('/packages/umrah-package/book', $this->form(['travel_month' => null]))->assertSessionHasErrors('travel_month');
        $this->post('/packages/umrah-package/book', $this->form(['travel_year' => 2030]))->assertSessionHasErrors('travel_year');

        $this->post('/packages/umrah-package/book', $this->form(['travel_month' => 2, 'travel_year' => 2027]))->assertRedirect();
        $booking = Booking::firstOrFail();
        $this->assertSame([2, 2027], [$booking->travel_month, $booking->travel_year]);

        $this->get($booking->publicUrl())->assertSeeText('February 2027');
        $this->asAdmin($this->superAdmin())->getJson("/api/admin/bookings/{$booking->id}")->assertJsonPath('data.travel_date', 'February 2027');
        Mail::assertSent(BookingReceived::class, fn ($mail) => str_contains($mail->render(), 'February 2027'));

        Carbon::setTestNow();
    }

    public function test_hajj_bookings_ask_for_a_hajj_year_from_2028(): void
    {
        Carbon::setTestNow('2026-10-16 12:00:00');
        $package = Package::where('slug', 'hajj-regular-package')->firstOrFail();
        $form = $this->form(['option' => $this->price($package)->id, 'travel_month' => null]);

        $this->get('/packages/hajj-regular-package/book')->assertOk()
            ->assertSeeText('Hajj year')
            ->assertSeeText('Hajj 2028')
            ->assertSee('<option value="2028">', false)
            ->assertDontSee('<option value="2027">', false)
            ->assertDontSeeText('Travel month');

        $this->post('/packages/hajj-regular-package/book', [...$form, 'travel_year' => 2027])->assertSessionHasErrors('travel_year');
        $this->post('/packages/hajj-regular-package/book', [...$form, 'travel_year' => 2028])->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame([null, 2028], [$booking->travel_month, $booking->travel_year]);
        $this->get($booking->publicUrl())->assertSeeText('Hajj 2028');

        Carbon::setTestNow();
    }

    public function test_packages_can_hide_their_price(): void
    {
        $this->enableSslCommerz();
        $package = $this->package();
        $amount = Money::html($package->min_price)->toHtml();

        // Admins switch the price off; the list and the editor report it.
        $admin = $this->asAdmin($this->superAdmin());
        $data = $admin->getJson("/api/admin/packages/{$package->id}")->assertOk()->assertJsonPath('data.show_price', true)->json('data');
        $admin->putJson("/api/admin/packages/{$package->id}", [...$data, 'show_price' => false, 'advance_type' => 'fixed', 'advance_value' => 20000])->assertOk();
        $admin->getJson('/api/admin/packages')->assertOk()->assertJsonPath('data.0.show_price', false);
        $package->refresh();
        $this->assertFalse($package->show_price);

        // The website shows "Price on request" and no amounts, also in the search data.
        $page = $this->get($package->url())->assertOk()->assertSeeText('Price on request')->assertSeeText('See options & hotels')->getContent();
        $this->assertStringNotContainsString($amount, $page);
        $this->assertStringNotContainsString('AggregateOffer', $page);
        $this->get('/umrah')->assertOk()->assertSeeText('Price on request');

        // Booking: no prices on the form, no online payment, even when asked for.
        $form = $this->get('/packages/umrah-package/book')->assertOk()->assertSeeText('Our team will call you to confirm the price and how to pay.')->getContent();
        $this->assertStringNotContainsString($amount, $form);
        $this->assertStringNotContainsString('Pay the advance now', $form);
        $this->post('/packages/umrah-package/book', $this->form(['payment' => 'now']))->assertInvalid('payment');

        // Still JSON from the admin calls above: the background post gets where to go next.
        $this->post('/packages/umrah-package/book', $this->form())->assertOk()->assertJsonStructure(['redirect']);
        $booking = Booking::firstOrFail();
        $this->assertTrue($booking->hidesPrice());
        $this->assertGreaterThan(0, (float) $booking->total, 'The office still gets the amounts.');

        $status = $this->get($booking->publicUrl())->assertOk()->assertSeeText('Price on request')->assertDontSeeText('Balance')->getContent();
        $this->assertStringNotContainsString('Pay advance', $status);
        $this->post(URL::signedRoute('bookings.pay', ['booking' => $booking->reference]))->assertRedirect($booking->publicUrl());

        Mail::assertSent(BookingReceived::class, fn ($mail) => ! str_contains($mail->render(), 'Balance'));
        Mail::assertSent(BookingPlaced::class, fn ($mail) => str_contains($mail->render(), 'Balance'));
    }

    public function test_booking_validation_and_closed_packages(): void
    {
        $other = Package::where('slug', '!=', 'umrah-package')->firstOrFail();

        $this->post('/packages/umrah-package/book', $this->form(['option' => $this->price($other)->id, 'agree' => null, 'travelers' => 0]))
            ->assertSessionHasErrors(['option', 'agree', 'travelers']);

        $this->post('/packages/umrah-package/book', $this->form(['payment' => 'now', 'email' => '']))
            ->assertSessionHasErrors('email');

        // #22: a larger group is told the limit plainly.
        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 500]))
            ->assertSessionHasErrors(['travelers' => 'Book 1 to 50 travelers at a time. For a larger group, please call us.']);

        // Email is required even when paying later: the confirmation and every update go there.
        $this->post('/packages/umrah-package/book', $this->form(['payment' => 'later', 'email' => '']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Booking::count());

        $this->package()->update(['booking_ends_on' => now()->subDay()]);
        $this->get('/packages/umrah-package/book')->assertNotFound();
        $this->post('/packages/umrah-package/book', $this->form())->assertNotFound();
    }

    public function test_phone_and_email_must_be_real(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        // #21: the number from the report, a short one and a landline are refused; so is an email without a domain ending.
        foreach (['01245686666666666', '0171100000', '029876543', 'call me'] as $phone) {
            $this->post('/packages/umrah-package/book', $this->form(['phone' => $phone]))
                ->assertSessionHasErrors(['phone' => 'Enter a valid mobile number, e.g. 01712-345678, or an international number starting with +.']);
        }
        foreach (['a@b', 'karim@example', 'not an email'] as $email) {
            $this->post('/packages/umrah-package/book', $this->form(['email' => $email]))
                ->assertSessionHasErrors(['email' => 'Enter a valid email address, e.g. name@example.com.']);
        }
        $this->assertSame(0, Booking::count());

        // Bangladeshi mobiles with or without +88, and international mobiles, are taken.
        foreach (['01711-000000', '+8801811000000', '+971501234567', '+44 7911 123456'] as $phone) {
            $this->post('/packages/umrah-package/book', $this->form(['phone' => $phone, 'travelers' => 1]))->assertRedirect();
        }
        $this->assertSame(4, Booking::count());
    }

    public function test_the_form_posts_in_the_background_and_keeps_the_details_on_errors(): void
    {
        Storage::fake('local');

        // #19/#20: problems come back as field errors (JSON), so the page keeps the visitor's files and details.
        $this->postJson('/packages/umrah-package/book', $this->form([
            'phone' => '01245686666666666',
            'passport' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            'nid' => [UploadedFile::fake()->create('nid.pdf', 6 * 1024, 'application/pdf')],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['phone', 'passport.0', 'nid.0']);

        $this->postJson('/packages/umrah-package/book', $this->form([
            'passport' => array_fill(0, 4, UploadedFile::fake()->create('passport.pdf', 4 * 1024, 'application/pdf')),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['passport' => 'The documents are too large together. Upload up to 15 MB in total.']);
        $this->assertSame(0, Booking::count());

        // Success says where to go next: the private booking page.
        $response = $this->postJson('/packages/umrah-package/book', $this->form())->assertOk();
        $this->assertSame(Booking::firstOrFail()->publicUrl(), $response->json('redirect'));

        // The page carries the same limits for the instant checks.
        $this->get('/packages/umrah-package/book')->assertOk()
            ->assertSee('data-validate="phone"', false)
            ->assertSee('data-validate="email"', false)
            ->assertSee('maxTotalBytes', false)
            ->assertSee('acceptFiles', false);
    }

    public function test_documents_are_private_and_only_for_admins(): void
    {
        Storage::fake('local');

        $this->post('/packages/umrah-package/book', $this->form([
            'passport' => [UploadedFile::fake()->image('passport.jpg')],
            'nid' => [UploadedFile::fake()->create('nid.pdf', 200, 'application/pdf')],
        ]))->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertCount(2, $booking->documents);
        $document = $booking->documents->firstWhere('kind', 'passport');
        Storage::disk('local')->assertExists($document->path);

        $url = "/api/admin/bookings/{$booking->id}/documents/{$document->id}";
        $this->asAdmin($this->adminWith(['bookings.view']))->get($url)->assertOk()->assertHeader('Cache-Control');
        $this->asAdmin($this->adminWith(['inquiries.view']))->get($url)->assertForbidden();

        // Deleting moves the booking and its documents to the Trash; the files stay.
        $this->asAdmin($this->superAdmin())->deleteJson("/api/admin/bookings/{$booking->id}")->assertNoContent();
        $this->assertSoftDeleted($booking);
        $this->assertSoftDeleted($document);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_sslcommerz_advance_payment_is_verified_with_the_gateway(): void
    {
        $this->enableSslCommerz();
        $this->package()->update(['advance_type' => 'fixed', 'advance_value' => 20000]);

        Http::fake([
            'sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc', 'sessionkey' => 'abc']),
        ]);

        $this->post('/packages/umrah-package/book', $this->form(['payment' => 'now']))
            ->assertRedirect('https://sandbox.sslcommerz.com/pay/abc');

        $booking = Booking::firstOrFail();
        $payment = $booking->payments()->firstOrFail();
        $this->assertSame('initiated', $payment->status);
        $this->assertSame(40000.0, (float) $payment->amount);
        $this->assertStringNotContainsString('secret-pass', json_encode($payment->log));

        Http::fake([
            'sandbox.sslcommerz.com/validator/*' => Http::response([
                'status' => 'VALID', 'tran_id' => $payment->transaction_id, 'val_id' => 'V1',
                'amount' => '40000.00', 'currency_type' => 'BDT', 'currency_amount' => '40000.00', 'bank_tran_id' => 'BANK1', 'risk_level' => '0',
            ]),
        ]);

        $this->post('/payments/sslcommerz/success', ['tran_id' => $payment->transaction_id, 'val_id' => 'V1', 'status' => 'VALID'])
            ->assertRedirect($booking->publicUrl());

        $booking->refresh();
        $this->assertSame('advance_paid', $booking->status);
        $this->assertSame(40000.0, (float) $booking->paid_amount);
        $this->assertSame('BANK1', $payment->fresh()->gateway_reference);

        // The IPN for the same payment changes nothing and sends no second receipt.
        $this->post('/payments/sslcommerz/ipn', ['tran_id' => $payment->transaction_id, 'val_id' => 'V1', 'status' => 'VALID'])->assertOk();
        $this->assertSame(40000.0, (float) $booking->fresh()->paid_amount);
        Mail::assertSent(PaymentReceived::class, 1);
    }

    public function test_unconfirmed_or_mismatched_sslcommerz_callbacks_are_not_paid(): void
    {
        $this->enableSslCommerz();
        $this->package()->update(['advance_type' => 'percentage', 'advance_value' => 50]);
        Http::fake(['sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/x'])]);
        $this->post('/packages/umrah-package/book', $this->form(['payment' => 'now']));
        $payment = Payment::firstOrFail();

        // A forged "success" post: the validator does not know the val_id.
        Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response(['status' => 'INVALID_TRANSACTION'])]);
        $this->post('/payments/sslcommerz/success', ['tran_id' => $payment->transaction_id, 'val_id' => 'FAKE', 'status' => 'VALID']);
        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(0.0, (float) Booking::first()->paid_amount);

        // A valid transaction for a different amount is rejected too.
        $second = Booking::first()->payments()->create(['method' => 'sslcommerz', 'is_online' => true, 'status' => 'initiated', 'amount' => 1000, 'transaction_id' => 'T-2']);
        Http::fake(['sandbox.sslcommerz.com/validator/*' => Http::response(['status' => 'VALID', 'tran_id' => 'T-2', 'amount' => '10.00', 'currency_type' => 'BDT', 'currency_amount' => '10.00'])]);
        $this->post('/payments/sslcommerz/success', ['tran_id' => 'T-2', 'val_id' => 'V2']);
        $this->assertSame('failed', $second->fresh()->status);

        $this->post('/payments/sslcommerz/success', ['tran_id' => 'UNKNOWN', 'val_id' => 'V'])->assertNotFound();
        Mail::assertNotSent(PaymentReceived::class);
    }

    public function test_dgepay_payment_is_confirmed_by_the_status_api(): void
    {
        settings()->set('payment', [
            'active_gateway' => 'dgepay',
            'dgepay_mode' => 'sandbox',
            'dgepay_client_id' => 'client',
            'dgepay_client_secret' => '0123456789abcdef',
            'dgepay_client_api_key' => 'api-key',
        ]);
        $this->package()->update(['advance_type' => 'fixed', 'advance_value' => 10000]);

        Http::fake([
            '*/payment_gateway/authenticate' => Http::response(['data' => ['access_token' => 'jwt']]),
            '*/payment_gateway/initiate_payment' => Http::response(['data' => ['webview_url' => 'https://pay.dgepay.test/w/1']]),
        ]);

        $this->post('/packages/umrah-package/book', $this->form(['payment' => 'now', 'travelers' => 1]))->assertRedirect('https://pay.dgepay.test/w/1');
        $payment = Payment::firstOrFail();

        Http::assertSent(fn ($request) => str_contains(strtolower($request->url()), 'api-uat.dgepay.net') && str_contains($request->url(), 'initiate_payment')
            && $request->hasHeader('Signature') && $request->hasHeader('Authorization', 'Bearer jwt'));

        Http::fake([
            '*/payment_gateway/authenticate' => Http::response(['data' => ['access_token' => 'jwt']]),
            '*/payment_gateway/check_transaction_status' => Http::response(['data' => [
                'status_code' => 3, 'unique_txn_id' => $payment->transaction_id, 'amount' => 10000, 'txn_number' => 'DG-1',
            ]]),
        ]);

        $this->get("/payments/dgepay/return/{$payment->transaction_id}?data=anything")->assertRedirect($payment->booking->publicUrl());

        $this->assertTrue($payment->fresh()->isPaid());
        $this->assertSame('DG-1', $payment->fresh()->gateway_reference);
        $this->assertSame('advance_paid', $payment->booking->fresh()->status);
    }

    public function test_dgepay_signature_and_encryption(): void
    {
        settings()->set('payment', ['dgepay_client_secret' => '0123456789abcdef', 'dgepay_client_api_key' => 'api-key']);
        $gateway = app(DgePayGateway::class);

        // Sorted keys, numbers with one decimal, nulls as "null".
        $expected = base64_encode(hash_hmac('sha256', 'amount1500.0notenullunique_txn_idT1', 'api-key', true));
        $this->assertSame($expected, $gateway->signature(['unique_txn_id' => 'T1', 'amount' => 1500, 'note' => null]));

        $encrypted = openssl_encrypt(json_encode(['status_code' => '3', 'unique_txn_id' => 'T1']), 'AES-128-ECB', '0123456789abcdef', 0);
        $this->assertSame(['status_code' => '3', 'unique_txn_id' => 'T1'], $gateway->decrypt(str_replace('+', ' ', $encrypted)));
    }

    public function test_admin_records_offline_payments_and_manages_the_booking(): void
    {
        $this->package()->update(['advance_type' => 'fixed', 'advance_value' => 20000]);
        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1]));
        $booking = Booking::firstOrFail();
        $admin = $this->adminWith(['bookings.view', 'bookings.update', 'payments.create']);

        $this->asAdmin($admin)->getJson('/api/admin/bookings?search=Karim')->assertOk()->assertJsonCount(1, 'data');
        // Status counts are whole numbers (not formatted like the booking total).
        $this->assertSame(1, $this->asAdmin($admin)->getJson('/api/admin/bookings')->json('counts.'.$booking->fresh()->status));
        $this->asAdmin($admin)->getJson("/api/admin/bookings/{$booking->id}")->assertOk()->assertJsonPath('data.reference', $booking->reference);
        $this->assertNotNull($booking->fresh()->read_at);

        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => 20000, 'method' => 'bank', 'paid_at' => now()->subHour()->toDateTimeString(), 'reference' => 'SLIP-9'])
            ->assertCreated()->assertJsonPath('data.status', 'advance_paid');

        // More than the balance is refused; the exact balance is not.
        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => $booking->fresh()->balance() + 1, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])
            ->assertJsonValidationErrors('amount');

        $payment = $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => $booking->fresh()->balance(), 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])
            ->assertCreated()->assertJsonPath('data.status', 'fully_paid')->assertJsonPath('data.balance', 0)->json('data.payments.0.id');

        // Removing a payment steps the status back down with the money.
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$payment}")
            ->assertOk()->assertJsonPath('data.paid_amount', 20000)->assertJsonPath('data.status', 'advance_paid');

        $first = $this->asAdmin($admin)->getJson("/api/admin/bookings/{$booking->id}")->json('data.payments.0.id');
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$first}")
            ->assertOk()->assertJsonPath('data.paid_amount', 0)->assertJsonPath('data.status', 'pending');

        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'cancelled', 'admin_notes' => 'Customer changed plans'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $viewer = $this->adminWith(['bookings.view']);
        $this->asAdmin($viewer)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => 1, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])->assertForbidden();
        $this->asAdmin($viewer)->deleteJson("/api/admin/bookings/{$booking->id}")->assertForbidden();
        $this->assertStringContainsString($booking->reference, $this->asAdmin($viewer)->get('/api/admin/bookings/export')->assertOk()->streamedContent());
    }

    public function test_a_deleted_payment_cannot_come_back_once_the_booking_is_paid_again(): void
    {
        // #40: delete a payment, record a new one, then try to restore the old one from the Trash.
        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1]));
        $booking = Booking::firstOrFail();
        $total = (float) $booking->total;
        $admin = $this->adminWith(['bookings.view', 'bookings.update', 'payments.create']);
        $super = $this->superAdmin();

        $old = $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => $total, 'method' => 'bank', 'paid_at' => now()->toDateTimeString()])
            ->assertCreated()->json('data.payments.0.id');
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$old}")->assertOk();
        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => $total, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])
            ->assertCreated()->assertJsonPath('data.status', 'fully_paid');

        $row = collect($this->asAdmin($super)->getJson('/api/admin/trash?type=payments')->assertOk()->json('data'))->firstWhere('id', $old);
        $this->assertStringContainsString('above its', $row['blocker']);

        $this->asAdmin($super)->postJson("/api/admin/trash/payments/{$old}/restore")->assertStatus(422);
        $this->assertSoftDeleted(Payment::find($old) ?? Payment::withTrashed()->find($old));
        $this->assertEquals($total, (float) $booking->fresh()->paid_amount);

        // Without the newer payment the old one comes back as before.
        $newer = $booking->payments()->where('status', 'paid')->latest('id')->value('id');
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$newer}")->assertOk();
        $this->asAdmin($super)->postJson("/api/admin/trash/payments/{$old}/restore")->assertSuccessful();
        $this->assertEquals($total, (float) $booking->fresh()->paid_amount);
        $this->assertSame('fully_paid', $booking->fresh()->status);
    }

    public function test_the_status_follows_the_payments_both_ways(): void
    {
        // No advance on this package: any amount below the total is a partial payment.
        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1]));
        $booking = Booking::firstOrFail();
        $admin = $this->adminWith(['bookings.view', 'bookings.update', 'payments.create']);
        $total = (float) $booking->total;

        // Staff confirm it first; removing every payment later goes back to Confirmed, not Pending.
        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'confirmed'])->assertOk();

        $first = $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => 1000, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])
            ->assertCreated()->assertJsonPath('data.status', 'partially_paid')->json('data.payments.0.id');

        $second = $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => $total - 1000, 'method' => 'bank', 'paid_at' => now()->toDateTimeString()])
            ->assertCreated()->assertJsonPath('data.status', 'fully_paid')->json('data.payments.0.id');

        // Nothing more can be recorded once it is paid in full.
        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => 1, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])
            ->assertJsonValidationErrors('amount');

        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$second}")->assertOk()->assertJsonPath('data.status', 'partially_paid');
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$first}")->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.paid_amount', 0);

        // With an advance: below it is partial, the advance itself is "advance paid".
        $this->package()->update(['advance_type' => 'fixed', 'advance_value' => 20000]);
        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1, 'phone' => '01711-000001']));
        $withAdvance = Booking::latest('id')->firstOrFail();
        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$withAdvance->id}/payments", ['amount' => 5000, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])->assertJsonPath('data.status', 'partially_paid');
        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$withAdvance->id}/payments", ['amount' => 15000, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])->assertJsonPath('data.status', 'advance_paid');

        // Completed and cancelled bookings keep their status whatever happens to the payments.
        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$withAdvance->id}", ['status' => 'completed'])->assertOk();
        $id = $withAdvance->payments()->value('id');
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$withAdvance->id}/payments/{$id}")->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_online_payments_never_overpay_and_late_confirmations_are_flagged(): void
    {
        $this->enableSslCommerz();
        $this->package()->update(['advance_type' => 'fixed', 'advance_value' => 20000]);
        Http::fake(['sandbox.sslcommerz.com/gwprocess/*' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc', 'sessionkey' => 'abc'])]);

        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1, 'payment' => 'now']));
        $booking = Booking::firstOrFail();
        $online = $booking->payments()->firstOrFail();
        $this->assertSame('initiated', $online->status);

        // Staff record the whole balance at the office: the unfinished online payment is withdrawn.
        $admin = $this->adminWith(['bookings.view', 'bookings.update', 'payments.create']);
        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => (float) $booking->total, 'method' => 'bank', 'paid_at' => now()->toDateTimeString()])
            ->assertCreated()->assertJsonPath('data.status', 'fully_paid');
        $this->assertSame('cancelled', $online->fresh()->status);

        // Nothing is due, so online payment is no longer offered or started.
        $this->assertFalse(app(OnlinePayments::class)->canPay($booking->fresh()));
        $this->post(URL::signedRoute('bookings.pay', ['booking' => $booking->reference]))->assertRedirect($booking->publicUrl());
        $this->assertSame(2, $booking->payments()->count());

        // The gateway still confirms the withdrawn payment: the money was taken, so it is recorded and flagged.
        app(BookingService::class)->markPaid($online->fresh(), ['gateway_reference' => 'LATE1']);
        $detail = $this->asAdmin($admin)->getJson("/api/admin/bookings/{$booking->id}")->assertOk()->json('data');
        $this->assertSame(20000, (int) $detail['overpaid']);
        $this->assertContains('overpaid', array_column($detail['activity'], 'event'));
        $this->assertContains('payment_withdrawn', array_column($detail['activity'], 'event'));

        // The online amount is never more than the balance, even with an advance set above the price.
        $fresh = (new Booking)->forceFill(['total' => 30000, 'advance_amount' => 45000, 'paid_amount' => 0]);
        $this->assertSame(30000.0, app(OnlinePayments::class)->amountDue($fresh));
    }

    public function test_each_booking_keeps_its_own_activity_log(): void
    {
        Storage::fake('local');
        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1, 'passport' => [UploadedFile::fake()->image('passport.jpg')]]));
        $other = $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1, 'phone' => '01711-000002']));
        [$booking, $second] = Booking::orderBy('id')->get()->all();
        $admin = $this->adminWith(['bookings.view', 'bookings.update', 'payments.create']);

        // The customer opens their page (logged once per half hour) and finds it with Find your booking.
        $this->get($booking->publicUrl())->assertOk();
        $this->get($booking->publicUrl())->assertOk();
        $this->post('/booking/status', ['reference' => $booking->reference, 'contact' => 'karim@example.com']);

        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'confirmed', 'admin_notes' => 'Called him', 'customer_message' => 'See you soon.'])->assertOk();
        $paymentId = $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => 500, 'method' => 'cash', 'paid_at' => now()->toDateTimeString(), 'reference' => 'R-1'])->json('data.payments.0.id');
        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$paymentId}")->assertOk();
        $document = $booking->documents()->firstOrFail();
        $this->asAdmin($admin)->get("/api/admin/bookings/{$booking->id}/documents/{$document->id}")->assertOk();

        $activity = collect($this->asAdmin($admin)->getJson("/api/admin/bookings/{$booking->id}")->json('data.activity'));
        $events = $activity->pluck('event');

        foreach (['created', 'email_sent', 'customer_viewed', 'customer_lookup', 'status_changed', 'notes_changed', 'message_sent', 'payment_recorded', 'status_auto', 'payment_removed', 'document_opened'] as $event) {
            $this->assertContains($event, $events, "missing {$event}");
        }
        $this->assertSame(1, $events->filter(fn ($e) => $e === 'customer_viewed')->count());
        $this->assertSame(['from' => 'pending', 'to' => 'confirmed', 'notify' => true], $activity->firstWhere('event', 'status_changed')['details']);
        $this->assertSame($admin->name, $activity->firstWhere('event', 'payment_removed')['by']);
        $this->assertNull($activity->firstWhere('event', 'customer_viewed')['by']);

        // Another booking's log is its own.
        $this->assertSame(['email_sent', 'email_sent', 'created'], collect($this->asAdmin($admin)->getJson("/api/admin/bookings/{$second->id}")->json('data.activity'))->pluck('event')->all());
    }

    public function test_customers_are_emailed_every_update_with_the_latest_details(): void
    {
        $this->package()->update(['advance_type' => 'fixed', 'advance_value' => 20000]);
        $this->post('/packages/umrah-package/book', $this->form(['travelers' => 1]));
        $booking = Booking::firstOrFail();
        $admin = $this->adminWith(['bookings.view', 'bookings.update', 'payments.create']);

        // The booking itself starts the history, and its email links to the lookup page.
        $this->assertSame(['created'], $booking->updates()->pluck('kind')->all());
        $this->assertNotNull($booking->updates()->first()->emailed_at);
        Mail::assertSent(BookingReceived::class, fn ($mail) => str_contains($mail->render(), route('bookings.status')));

        // A new status with a message: emailed with the latest details, kept in the history.
        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'confirmed', 'admin_notes' => 'Staff only', 'customer_message' => 'Your visa file is ready.'])
            // The response comes first: the email goes out right after it, so the update is "to be emailed", then emailed.
            ->assertOk()->assertJsonPath('data.updates.0.kind', 'status')->assertJsonPath('data.updates.0.notify', true)->assertJsonPath('data.updates.0.emailed', false);
        $this->assertNotNull($booking->updates()->first()->emailed_at);
        Mail::assertSent(BookingUpdated::class, fn ($mail) => $mail->hasTo('karim@example.com')
            && str_contains($mail->render(), 'Your visa file is ready.')
            && str_contains($mail->render(), 'Confirmed')
            && ! str_contains($mail->render(), 'Staff only'));

        // Saving internal notes alone tells the customer nothing.
        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'confirmed', 'admin_notes' => 'Called back'])->assertOk();
        Mail::assertSent(BookingUpdated::class, 1);

        // A message with "Email the customer" unticked is recorded but not sent.
        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'confirmed', 'customer_message' => 'Flights are booked.', 'notify' => false])
            ->assertOk()->assertJsonPath('data.updates.0.kind', 'message')->assertJsonPath('data.updates.0.notify', false)->assertJsonPath('data.updates.0.emailed', false);
        Mail::assertSent(BookingUpdated::class, 1);

        // An office payment sends a receipt with the new balance.
        $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => 20000, 'method' => 'bank', 'paid_at' => now()->subHour()->toDateTimeString(), 'customer_message' => 'Thank you for the deposit.'])
            ->assertCreated()->assertJsonPath('data.updates.0.kind', 'payment')->assertJsonPath('data.updates.0.notify', true);
        $this->assertNotNull($booking->updates()->first()->emailed_at);
        Mail::assertSent(PaymentReceived::class, fn ($mail) => str_contains($mail->render(), 'Thank you for the deposit.'));

        // The customer's page shows the whole history; internal notes stay hidden.
        $this->get($booking->publicUrl())->assertOk()
            ->assertSeeTextInOrder(['Booking updates', 'Payment received', 'Flights are booked.', 'Your visa file is ready.', 'Booking received'])
            ->assertDontSeeText('Staff only');
    }

    public function test_customers_find_their_booking_with_reference_and_phone(): void
    {
        $this->post('/packages/umrah-package/book', $this->form());
        $booking = Booking::firstOrFail();

        $this->post('/booking/status', ['reference' => strtolower($booking->reference), 'contact' => '+8801711000000'])
            ->assertRedirect($booking->publicUrl());
        $this->post('/booking/status', ['reference' => $booking->reference, 'contact' => 'KARIM@example.com'])
            ->assertRedirect($booking->publicUrl());
        $this->post('/booking/status', ['reference' => $booking->reference, 'contact' => '01999999999'])
            ->assertSessionHasErrors('reference');

        // The page is "Find your booking", linked from the package pages.
        $this->get('/booking/status')->assertOk()->assertSeeText('Find your booking');
        $this->get('/packages/umrah-package')->assertOk()->assertSee(route('bookings.status'), false);
    }
}
