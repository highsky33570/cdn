<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function validate(string $token): bool
    {
        $secret = config('services.recaptcha.secret_key');

        if (! $secret) {
            return true;
        }

        try {
            $response = Http::asForm()
                // Without an explicit timeout a slow verifier stalls the request for
                // the default 30s, on login/registration/password-reset.
                ->timeout((int) config('services.recaptcha.timeout', 5))
                ->retry(2, 200, throw: false)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                ]);
        } catch (\Throwable $e) {
            return $this->unreachable($e->getMessage());
        }

        if ($response->failed()) {
            return $this->unreachable('HTTP '.$response->status());
        }

        // A definitive "not a human" answer is always honoured.
        return $response->json('success') === true;
    }

    /**
     * Distinguishes "the verifier said no" from "we could not ask it". The former
     * must always block; the latter would otherwise take down login, registration
     * and password reset for everyone whenever Google is unreachable. Those routes
     * are independently rate limited, so the captcha is defence in depth here.
     */
    private function unreachable(string $reason): bool
    {
        $failOpen = (bool) config('services.recaptcha.fail_open', true);

        Log::warning('recaptcha verification unreachable', [
            'reason' => $reason,
            'fail_open' => $failOpen,
        ]);

        return $failOpen;
    }
}
