<?php

namespace App\Domain\Forms;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google reCAPTCHA v3 verification. Active only when both keys are set.
 */
class Recaptcha
{
    public const MIN_SCORE = 0.5;

    public function enabled(): bool
    {
        return filled(settings('tracking.recaptcha_site_key')) && filled(settings('tracking.recaptcha_secret_key'));
    }

    public function passes(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $result = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', array_filter([
                'secret' => settings('tracking.recaptcha_secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]))->json();
        } catch (Throwable $e) {
            // If Google can't be reached, don't lose a genuine enquiry.
            report($e);

            return true;
        }

        return ($result['success'] ?? false) && ($result['score'] ?? 0) >= self::MIN_SCORE;
    }
}
