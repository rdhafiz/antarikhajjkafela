<?php

namespace Tests\Feature;

use App\Domain\Booking\BookingCalculator;
use App\Domain\Payments\Gateways\DgePayGateway;
use App\Mail\BookingPlaced;
use App\Mail\BookingReceived;
use App\Mail\PaymentReceived;
use App\Models\Booking;
use App\Models\Package;
use App\Models\PackagePrice;
use App\Models\Payment;
use Database\Seeders\PageSeeder;
use Database\Seeders\TravelSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    public function test_booking_validation_and_closed_packages(): void
    {
        $other = Package::where('slug', '!=', 'umrah-package')->firstOrFail();

        $this->post('/packages/umrah-package/book', $this->form(['option' => $this->price($other)->id, 'agree' => null, 'travelers' => 0]))
            ->assertSessionHasErrors(['option', 'agree', 'travelers']);

        $this->post('/packages/umrah-package/book', $this->form(['payment' => 'now', 'email' => '']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Booking::count());

        $this->package()->update(['booking_ends_on' => now()->subDay()]);
        $this->get('/packages/umrah-package/book')->assertNotFound();
        $this->post('/packages/umrah-package/book', $this->form())->assertNotFound();
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

        $this->asAdmin($this->superAdmin())->deleteJson("/api/admin/bookings/{$booking->id}")->assertNoContent();
        Storage::disk('local')->assertMissing($document->path);
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

        $payment = $this->asAdmin($admin)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => $booking->balance(), 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])
            ->assertCreated()->assertJsonPath('data.status', 'fully_paid')->assertJsonPath('data.balance', 0)->json('data.payments.0.id');

        $this->asAdmin($admin)->deleteJson("/api/admin/bookings/{$booking->id}/payments/{$payment}")->assertOk()->assertJsonPath('data.paid_amount', 20000);

        $this->asAdmin($admin)->putJson("/api/admin/bookings/{$booking->id}", ['status' => 'cancelled', 'admin_notes' => 'Customer changed plans'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        $viewer = $this->adminWith(['bookings.view']);
        $this->asAdmin($viewer)->postJson("/api/admin/bookings/{$booking->id}/payments", ['amount' => 1, 'method' => 'cash', 'paid_at' => now()->toDateTimeString()])->assertForbidden();
        $this->asAdmin($viewer)->deleteJson("/api/admin/bookings/{$booking->id}")->assertForbidden();
        $this->assertStringContainsString($booking->reference, $this->asAdmin($viewer)->get('/api/admin/bookings/export')->assertOk()->streamedContent());
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
    }
}
