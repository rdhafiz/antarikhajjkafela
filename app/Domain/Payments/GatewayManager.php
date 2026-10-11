<?php

namespace App\Domain\Payments;

use InvalidArgumentException;

/**
 * Resolves the gateway drivers and the one the admin has switched on.
 */
class GatewayManager
{
    public function get(string $key): PaymentGateway
    {
        $class = config("payments.gateways.{$key}");

        if (! $class) {
            throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
        }

        return app($class);
    }

    /**
     * The active gateway, or null when online payment is off or not configured.
     */
    public function active(): ?PaymentGateway
    {
        $key = settings('payment.active_gateway');

        if (blank($key) || ! config("payments.gateways.{$key}")) {
            return null;
        }

        $gateway = $this->get($key);

        return $gateway->isConfigured() ? $gateway : null;
    }
}
