<?php

namespace App\Domain\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * An online payment gateway. Drivers are listed in config/payments.php.
 */
interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    /**
     * Whether the credentials needed to take payments are present.
     */
    public function isConfigured(): bool;

    /**
     * Start a payment and return the gateway page to send the customer to.
     *
     * @throws GatewayException when the gateway refuses or cannot be reached
     */
    public function initiate(Payment $payment, Booking $booking): string;

    /**
     * Confirm a payment with the gateway's own API. Callback data is only
     * used to find the transaction, never trusted on its own.
     */
    public function verify(Payment $payment, Request $request): VerificationResult;

    /**
     * The payment's transaction id taken from a callback request, if present.
     */
    public function transactionIdFrom(Request $request): ?string;
}
