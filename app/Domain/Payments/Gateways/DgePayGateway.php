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
 * DGePay payment gateway (API v3).
 *
 * Every call first gets a short-lived token (HTTP Basic with the client id
 * and secret). Request bodies are AES-128-ECB encrypted with the client
 * secret and signed with HMAC-SHA256 using the client API key. The customer
 * returns with an encrypted `data` parameter; the payment is only accepted
 * after the transaction-status API confirms it.
 */
class DgePayGateway implements PaymentGateway
{
    public const STATUS_SUCCESS = '3';

    public const STATUS_CANCELLED = '8';

    public function key(): string
    {
        return 'dgepay';
    }

    public function label(): string
    {
        return 'DGePay';
    }

    public function isConfigured(): bool
    {
        return filled(settings('payment.dgepay_client_id'))
            && filled(settings('payment.dgepay_client_secret'))
            && filled(settings('payment.dgepay_client_api_key'));
    }

    public function initiate(Payment $payment, Booking $booking): string
    {
        $payload = [
            'amount' => (float) $payment->amount,
            'customer_token' => null,
            'note' => Str::limit($booking->reference.' – '.$booking->snap('package', 'en'), 120, ''),
            'payee_information' => null,
            'payment_method' => null,
            'redirect_url' => route('payments.dgepay.return', $payment->transaction_id),
            'unique_txn_id' => $payment->transaction_id,
            'meta_data' => null,
            'unique_user_reference' => $booking->reference,
        ];

        $data = $this->call($payment, 'initiate', '/payment_gateway/initiate_payment', $payload);
        $url = $data['data']['webview_url'] ?? null;

        if (blank($url)) {
            throw new GatewayException('DGePay refused the payment: '.($data['error'][0] ?? $data['message'] ?? 'unknown reason'));
        }

        return $url;
    }

    public function verify(Payment $payment, Request $request): VerificationResult
    {
        try {
            $data = $this->call($payment, 'status', '/payment_gateway/check_transaction_status', ['unique_txn_id' => $payment->transaction_id]);
        } catch (GatewayException $e) {
            return VerificationResult::pending($e->getMessage());
        }

        $result = $data['data'] ?? [];
        $status = (string) ($result['status_code'] ?? '');

        if ($status === self::STATUS_CANCELLED) {
            return VerificationResult::failed('Cancelled by the customer.', VerificationResult::CANCELLED);
        }

        if ($status !== self::STATUS_SUCCESS) {
            return blank($status)
                ? VerificationResult::pending('DGePay has not finished this payment yet.')
                : VerificationResult::failed('DGePay reported: '.($result['message'] ?? "status {$status}"));
        }

        if (($result['unique_txn_id'] ?? null) !== $payment->transaction_id || abs((float) ($result['amount'] ?? 0) - (float) $payment->amount) > 0.009) {
            return VerificationResult::failed('The confirmed transaction does not match this payment.');
        }

        return VerificationResult::paid($result['txn_number'] ?? $result['third_party_txn_number'] ?? null);
    }

    public function transactionIdFrom(Request $request): ?string
    {
        // The return URL carries our transaction id in its path.
        return $request->route('transaction');
    }

    /**
     * Sign, encrypt and send a request with a fresh access token.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(Payment $payment, string $event, string $path, array $payload): array
    {
        try {
            $token = $this->token();

            $response = Http::timeout(config('payments.timeout'))
                ->withToken($token)
                ->withHeaders(['Signature' => $this->signature($payload)])
                ->withBody($this->encrypt($payload), 'application/json')
                ->post($this->baseUrl().$path);
        } catch (ConnectionException $e) {
            $payment->addLog("{$event}.error", ['message' => $e->getMessage()]);
            throw new GatewayException('DGePay could not be reached.', previous: $e);
        }

        $data = (array) $response->json();
        $payment->addLog($event, ['request' => $payload, 'status' => $response->status(), 'response' => $data]);

        if (! $response->successful()) {
            throw new GatewayException('DGePay returned an error: '.($data['error'][0] ?? $data['message'] ?? $response->status()));
        }

        return $data;
    }

    private function token(): string
    {
        $id = (string) settings('payment.dgepay_client_id');
        $secret = (string) settings('payment.dgepay_client_secret');

        $response = Http::timeout(config('payments.timeout'))
            ->withBasicAuth($id, $secret)
            ->asJson()
            ->post($this->baseUrl().'/payment_gateway/authenticate', ['client_id' => $id, 'client_secret' => $secret]);

        $token = $response->json('data.access_token');

        if (! $response->successful() || blank($token)) {
            throw new GatewayException('DGePay authentication failed: '.($response->json('error.0') ?? $response->status()));
        }

        return $token;
    }

    /**
     * HMAC-SHA256 (base64) over the alphabetically sorted, flattened
     * key/value string, with numbers written with one decimal ("1500.0").
     *
     * @param  array<string, mixed>  $payload
     */
    public function signature(array $payload): string
    {
        $flat = str_replace(['{', '}', '"', ':', ' ', ','], '', $this->flatten($payload));

        return base64_encode(hash_hmac('sha256', $flat, (string) settings('payment.dgepay_client_api_key'), true));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function flatten(array $data): string
    {
        ksort($data);
        $out = '';

        foreach ($data as $key => $value) {
            $out .= $key.match (true) {
                is_array($value) => $this->flatten($value),
                $value === null => 'null',
                is_bool($value) => $value ? 'true' : 'false',
                is_int($value), is_float($value) => number_format((float) $value, 1, '.', ''),
                default => (string) $value,
            };
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function encrypt(array $payload): string
    {
        return (string) openssl_encrypt((string) json_encode($payload), 'AES-128-ECB', $this->aesKey(), 0);
    }

    /**
     * Decrypt the `data` parameter DGePay adds to the return URL.
     *
     * @return array<string, mixed>|null
     */
    public function decrypt(string $data): ?array
    {
        // Query strings turn "+" into spaces.
        $plain = openssl_decrypt(str_replace(' ', '+', $data), 'AES-128-ECB', $this->aesKey(), 0);
        $decoded = $plain === false ? null : json_decode($plain, true);

        return is_array($decoded) ? Arr::only($decoded, ['status_code', 'unique_txn_id', 'message', 'amount', 'txn_number', 'payment_method']) : null;
    }

    private function aesKey(): string
    {
        return substr((string) settings('payment.dgepay_client_secret'), 0, 16);
    }

    private function baseUrl(): string
    {
        return rtrim(config('payments.dgepay.'.(settings('payment.dgepay_mode') === 'live' ? 'live' : 'sandbox')), '/');
    }
}
