<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\GatewayException;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\VerificationResult;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * SSLCommerz (API v4): hosted checkout, then the Order Validation API
 * confirms every success callback and IPN.
 */
class SslCommerzGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'sslcommerz';
    }

    public function label(): string
    {
        return 'SSLCommerz';
    }

    public function isConfigured(): bool
    {
        return filled(settings('payment.sslcommerz_store_id')) && filled(settings('payment.sslcommerz_store_password'));
    }

    public function initiate(Payment $payment, Booking $booking): string
    {
        $params = [
            'store_id' => settings('payment.sslcommerz_store_id'),
            'store_passwd' => settings('payment.sslcommerz_store_password'),
            'total_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'currency' => $payment->currency,
            'tran_id' => $payment->transaction_id,
            'success_url' => route('payments.sslcommerz.callback', 'success'),
            'fail_url' => route('payments.sslcommerz.callback', 'fail'),
            'cancel_url' => route('payments.sslcommerz.callback', 'cancel'),
            'ipn_url' => route('payments.sslcommerz.ipn'),
            'cus_name' => Str::limit($booking->customer_name, 50, ''),
            'cus_email' => $booking->customer_email ?: 'no-email@'.parse_url(config('app.url'), PHP_URL_HOST),
            'cus_add1' => Str::limit($booking->customer_address ?: 'Bangladesh', 50, ''),
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $booking->customer_phone,
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'product_name' => Str::limit((string) $booking->snap('package', 'en'), 90, ''),
            'product_category' => 'Travel',
            'product_profile' => 'travel-vertical',
            'hotel_name' => Str::limit((string) $booking->snap('option', 'en'), 50, '') ?: 'Package hotel',
            'length_of_stay' => (string) ($booking->snapshot['duration_days'] ?? 1),
            'check_in_time' => 'As per package',
            'hotel_city' => 'Makkah',
            'value_a' => $booking->reference,
        ];

        try {
            $response = Http::asForm()->timeout(config('payments.timeout'))->post($this->baseUrl().'/gwprocess/v4/api.php', $params);
        } catch (ConnectionException $e) {
            $payment->addLog('initiate.error', ['message' => $e->getMessage()]);
            throw new GatewayException('SSLCommerz could not be reached.', previous: $e);
        }

        $data = (array) $response->json();
        $payment->addLog('initiate', ['request' => Arr::except($params, ['store_passwd']), 'status' => $response->status(), 'response' => Arr::only($data, ['status', 'failedreason', 'sessionkey', 'GatewayPageURL'])]);

        if (($data['status'] ?? null) !== 'SUCCESS' || blank($data['GatewayPageURL'] ?? null)) {
            throw new GatewayException('SSLCommerz refused the payment: '.($data['failedreason'] ?? 'unknown reason'));
        }

        return $data['GatewayPageURL'];
    }

    public function verify(Payment $payment, Request $request): VerificationResult
    {
        $valId = $request->input('val_id');
        $status = strtoupper((string) $request->input('status'));

        if (blank($valId)) {
            // Fail and cancel callbacks carry no validation id; nothing was paid.
            return VerificationResult::failed($status === 'CANCELLED' ? 'Cancelled by the customer.' : 'The payment was not completed.', $status === 'CANCELLED' ? VerificationResult::CANCELLED : VerificationResult::FAILED);
        }

        try {
            $response = Http::timeout(config('payments.timeout'))->get($this->baseUrl().'/validator/api/validationserverAPI.php', [
                'val_id' => $valId,
                'store_id' => settings('payment.sslcommerz_store_id'),
                'store_passwd' => settings('payment.sslcommerz_store_password'),
                'format' => 'json',
                'v' => 1,
            ]);
        } catch (ConnectionException $e) {
            $payment->addLog('validate.error', ['message' => $e->getMessage()]);

            return VerificationResult::pending('SSLCommerz could not be reached to confirm the payment.');
        }

        $data = (array) $response->json();
        $payment->addLog('validate', ['val_id' => $valId, 'status' => $response->status(), 'response' => Arr::only($data, ['status', 'tran_id', 'val_id', 'amount', 'currency_type', 'currency_amount', 'bank_tran_id', 'card_type', 'risk_level', 'risk_title'])]);

        if (! in_array($data['status'] ?? null, ['VALID', 'VALIDATED'], true)) {
            return VerificationResult::failed('SSLCommerz did not confirm the payment ('.($data['status'] ?? 'no status').').');
        }

        $amount = (float) ($data['currency_amount'] ?? $data['amount'] ?? 0);
        $currency = $data['currency_type'] ?? $data['currency'] ?? null;

        if (($data['tran_id'] ?? null) !== $payment->transaction_id || abs($amount - (float) $payment->amount) > 0.009 || $currency !== $payment->currency) {
            return VerificationResult::failed('The confirmed transaction does not match this payment.');
        }

        // Risky transactions (risk_level 1) are held for manual review in the SSLCommerz panel.
        if ((string) ($data['risk_level'] ?? '0') === '1') {
            return VerificationResult::pending('SSLCommerz flagged this payment for review.');
        }

        return VerificationResult::paid($data['bank_tran_id'] ?? $valId);
    }

    public function transactionIdFrom(Request $request): ?string
    {
        return $request->input('tran_id');
    }

    private function baseUrl(): string
    {
        return rtrim(config('payments.sslcommerz.'.(settings('payment.sslcommerz_mode') === 'live' ? 'live' : 'sandbox')), '/');
    }
}
