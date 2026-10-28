<?php

namespace App\Domain\Payments;

use App\Domain\Booking\BookingLog;
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
        private readonly BookingLog $log,
    ) {}

    /**
     * What an online payment would charge now: the advance still due, never more than the balance.
     */
    public function amountDue(Booking $booking): float
    {
        return round(min($booking->advanceDue(), $booking->balance()), 2);
    }

    /**
     * Once the advance is paid in full, the rest of the price can be paid
     * online too (bookings without an advance rule are paid at the office).
     */
    public function balanceDue(Booking $booking): float
    {
        return (float) $booking->advance_amount > 0 && $booking->advanceDue() < 0.005 ? round($booking->balance(), 2) : 0.0;
    }

    public function canPayBalance(Booking $booking): bool
    {
        return ! $booking->isClosed()
            && ! $booking->hidesPrice()
            && $this->balanceDue($booking) >= self::MINIMUM
            && $this->gateways->active() !== null;
    }

    /**
     * Whether the customer can pay the advance for this booking online now.
     */
    public function canPay(Booking $booking): bool
    {
        return ! $booking->isClosed()
            && ! $booking->hidesPrice()
            && $this->amountDue($booking) >= self::MINIMUM
            && $this->gateways->active() !== null;
    }

    /**
     * Create a payment for the outstanding advance (or, once that is paid, for
     * the balance or the part of it the customer chose) and return the gateway URL.
     *
     * @throws GatewayException
     */
    public function start(Booking $booking, ?float $amount = null): string
    {
        $gateway = $this->gateways->active() ?? throw new GatewayException('Online payment is not available.');

        // Re-checked here, not only when the button was shown: staff may have recorded a payment since.
        $booking->refresh();
        if ($this->canPay($booking)) {
            $amount = $this->amountDue($booking);
        } elseif ($this->canPayBalance($booking)) {
            $amount = round(min(max($amount ?? $this->balanceDue($booking), self::MINIMUM), $this->balanceDue($booking)), 2);
        } else {
            throw new GatewayException('Nothing is due online for this booking.');
        }

        $payment = $booking->payments()->create([
            'method' => $gateway->key(),
            'is_online' => true,
            'status' => 'initiated',
            'amount' => $amount,
            'currency' => $booking->currency,
            'transaction_id' => $booking->reference.'-'.Str::upper(Str::random(6)),
        ]);

        $this->log->record($booking, 'payment_started', ['amount' => (float) $payment->amount, 'gateway' => $payment->methodLabel(), 'transaction' => $payment->transaction_id]);

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
