<?php

namespace App\Domain\Payments;

use App\Domain\Booking\BookingService;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Starts online advance payments and settles them from gateway callbacks.
 */
class OnlinePayments
{
    // SSLCommerz does not accept smaller amounts.
    public const MINIMUM = 10;

    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly BookingService $bookings,
    ) {}

    /**
     * Whether the customer can pay the advance for this booking online now.
     */
    public function canPay(Booking $booking): bool
    {
        return ! $booking->isClosed()
            && ! $booking->hidesPrice()
            && $booking->advanceDue() >= self::MINIMUM
            && $this->gateways->active() !== null;
    }

    /**
     * Create a payment for the outstanding advance and return the gateway URL.
     *
     * @throws GatewayException
     */
    public function start(Booking $booking): string
    {
        $gateway = $this->gateways->active() ?? throw new GatewayException('Online payment is not available.');

        $payment = $booking->payments()->create([
            'method' => $gateway->key(),
            'is_online' => true,
            'status' => 'initiated',
            'amount' => $booking->advanceDue(),
            'currency' => $booking->currency,
            'transaction_id' => $booking->reference.'-'.Str::upper(Str::random(6)),
        ]);

        try {
            return $gateway->initiate($payment, $booking);
        } catch (GatewayException $e) {
            $this->bookings->markFailed($payment);
            report($e);

            throw $e;
        }
    }

    /**
     * Settle a payment from a gateway callback or IPN. Returns null when the
     * request does not belong to a known payment.
     */
    public function settle(string $gatewayKey, Request $request): ?Payment
    {
        $gateway = $this->gateways->get($gatewayKey);
        $transactionId = $gateway->transactionIdFrom($request);

        $payment = $transactionId
            ? Payment::where('transaction_id', $transactionId)->where('method', $gatewayKey)->first()
            : null;

        if (! $payment) {
            return null;
        }

        if ($payment->isPaid()) {
            return $payment;
        }

        $result = $gateway->verify($payment, $request);
        $payment->addLog('result', ['status' => $result->status, 'message' => $result->message]);

        match ($result->status) {
            VerificationResult::PAID => $this->bookings->markPaid($payment, ['gateway_reference' => $result->gatewayReference]),
            VerificationResult::FAILED, VerificationResult::CANCELLED => $this->bookings->markFailed($payment, $result->status),
            default => null,
        };

        return $payment->refresh();
    }
}
