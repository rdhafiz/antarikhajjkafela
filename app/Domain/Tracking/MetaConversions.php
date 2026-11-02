<?php

namespace App\Domain\Tracking;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends checkout and payment events to Meta's Conversions API from the server.
 * The customer's booking page keeps tracking off (its signed link must not
 * reach Meta), and a confirmation can arrive by IPN with no browser at all.
 * Only the package page's address is sent, and email and phone only hashed.
 */
class MetaConversions
{
    private const GRAPH_VERSION = 'v26.0';

    public function enabled(): bool
    {
        return ! app()->isLocal()
            && filled(settings('tracking.facebook_pixel_id'))
            && filled(settings('tracking.facebook_capi_token'));
    }

    /**
     * The browser details only the customer's own request has: a confirmation
     * from the gateway's server later can't supply them.
     *
     * @return array<string, string>
     */
    public static function visitor(Request $request): array
    {
        return array_filter([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fbp' => $request->cookie('_fbp'),
            'fbc' => $request->cookie('_fbc'),
        ]);
    }

    public function initiateCheckout(Payment $payment): void
    {
        $this->send('InitiateCheckout', "checkout-{$payment->id}", $payment);
    }

    public function purchase(Payment $payment): void
    {
        $this->send('Purchase', "purchase-{$payment->id}", $payment);
    }

    private function send(string $event, string $eventId, Payment $payment): void
    {
        if (! $this->enabled()) {
            return;
        }

        // After the response, and never in the way of a payment.
        defer(function () use ($event, $eventId, $payment) {
            try {
                $response = Http::timeout(5)->post(
                    'https://graph.facebook.com/'.self::GRAPH_VERSION.'/'.settings('tracking.facebook_pixel_id').'/events',
                    array_filter([
                        'data' => [$this->payload($event, $eventId, $payment)],
                        'test_event_code' => settings('tracking.facebook_test_event_code'),
                        'access_token' => settings('tracking.facebook_capi_token'),
                    ]),
                );

                if ($response->failed()) {
                    Log::warning('Meta Conversions API refused an event.', [
                        'event' => $eventId,
                        'status' => $response->status(),
                        'error' => $response->json('error.message'),
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Meta Conversions API could not be reached.', ['event' => $eventId, 'error' => $e->getMessage()]);
            }
        }, always: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $event, string $eventId, Payment $payment): array
    {
        $booking = $payment->booking;
        $package = $booking->package()->withTrashed()->first();

        return [
            'event_name' => $event,
            'event_time' => now()->timestamp,
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => $package?->url() ?? url('/'),
            'user_data' => $this->userData($booking, $payment->visitor ?? []),
            'custom_data' => array_filter([
                'currency' => $payment->currency,
                'value' => (float) $payment->amount,
                'order_id' => $payment->transaction_id,
                ...MetaContent::package($package),
            ]),
        ];
    }

    /**
     * @param  array<string, string>  $visitor
     * @return array<string, mixed>
     */
    private function userData(Booking $booking, array $visitor): array
    {
        $email = mb_strtolower(trim((string) $booking->customer_email));
        $phone = preg_replace('/\D+/', '', (string) $booking->customer_phone);

        // Stored numbers are normally international; a local one is Bangladeshi.
        if (str_starts_with($phone, '0')) {
            $phone = '88'.$phone;
        }

        return array_filter([
            'em' => $email !== '' ? [hash('sha256', $email)] : null,
            'ph' => $phone !== '' ? [hash('sha256', $phone)] : null,
            'client_ip_address' => $visitor['ip'] ?? null,
            'client_user_agent' => $visitor['user_agent'] ?? null,
            'fbp' => $visitor['fbp'] ?? null,
            'fbc' => $visitor['fbc'] ?? null,
        ]);
    }
}
