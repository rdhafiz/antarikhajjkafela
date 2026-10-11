<?php

namespace App\Http\Controllers\Site;

use App\Domain\Payments\OnlinePayments;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Where gateways send customers back, and server-to-server notifications.
 * Every outcome is confirmed with the gateway's API before it counts.
 */
class PaymentCallbackController extends Controller
{
    public function __construct(private readonly OnlinePayments $payments) {}

    /**
     * SSLCommerz posts the customer back to success, fail or cancel.
     */
    public function sslcommerz(Request $request, string $result): RedirectResponse
    {
        return $this->backToBooking($this->payments->settle('sslcommerz', $request));
    }

    /**
     * SSLCommerz instant payment notification (server to server).
     */
    public function sslcommerzIpn(Request $request): Response
    {
        $payment = $this->payments->settle('sslcommerz', $request);

        return response($payment ? 'OK' : 'UNKNOWN', $payment ? 200 : 404);
    }

    /**
     * DGePay sends the customer back with ?data=… (encrypted).
     */
    public function dgepay(Request $request, string $transaction): RedirectResponse
    {
        return $this->backToBooking($this->payments->settle('dgepay', $request));
    }

    private function backToBooking(?Payment $payment): RedirectResponse
    {
        abort_unless($payment, 404);

        return redirect()->to($payment->booking->publicUrl())->with('payment_result', match ($payment->status) {
            'paid' => 'paid',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            default => 'pending',
        });
    }
}
