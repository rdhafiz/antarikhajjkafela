<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingsPresenter;
use App\Domain\Settings\SettingsValidator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    /**
     * Credentials a gateway needs before it can be made active.
     */
    private const GATEWAY_CREDENTIALS = [
        'sslcommerz' => ['sslcommerz_store_id', 'sslcommerz_store_password'],
        'dgepay' => ['dgepay_client_id', 'dgepay_client_secret', 'dgepay_client_api_key'],
    ];

    public function __construct(
        private readonly Settings $settings,
        private readonly SettingsPresenter $presenter,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->presenter->navigation()]);
    }

    public function show(string $group): JsonResponse
    {
        abort_unless($this->settings->hasGroup($group), 404);

        return response()->json(['data' => $this->presenter->group($group)]);
    }

    public function update(Request $request, string $group, SettingsValidator $validator): JsonResponse
    {
        abort_unless($this->settings->hasGroup($group), 404);

        $values = $request->validate($validator->rules($group));

        if ($group === 'payment') {
            $this->ensureGatewayIsConfigured($values);
        }

        $this->settings->set($group, $values);

        activity('settings')
            ->causedBy($request->user())
            ->event('updated')
            ->withProperties(['group' => $group, 'keys' => array_keys($values)])
            ->log("Updated {$group} settings");

        return response()->json(['data' => $this->presenter->group($group)]);
    }

    /**
     * A gateway can only be activated once its credentials are saved
     * (either already stored, or submitted in the same request).
     *
     * @param  array<string, mixed>  $values
     */
    private function ensureGatewayIsConfigured(array $values): void
    {
        $gateway = $values['active_gateway'] ?? null;

        if (blank($gateway)) {
            return;
        }

        $missing = collect(self::GATEWAY_CREDENTIALS[$gateway] ?? [])
            ->reject(fn ($key) => filled($values[$key] ?? null) || filled($this->settings->get("payment.{$key}")));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'active_gateway' => 'Enter and save this gateway\'s credentials before making it active.',
            ]);
        }
    }
}
