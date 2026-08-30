<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;

trait FakesRcaAuth
{
    /** Construieste un JWT cu claim-ul 'exp' pe care il citeste RcaTokenManager. */
    protected function jwt(int $secondsUntilExpiry = 43200): string
    {
        $payload = json_encode(['exp' => Carbon::now()->addSeconds($secondsUntilExpiry)->timestamp]);

        return 'antet.'.rtrim(strtr(base64_encode($payload), '+/', '-_'), '=').'.semnatura';
    }

    /** Raspunsul pe care il da /auth. */
    protected function authResponse(string $token, ?string $expiresAtText = null): array
    {
        return [
            'error'  => false,
            'status' => 200,
            'data'   => [
                'token'         => $token,
                'refresh_token' => 'refresh-'.substr(md5($token), 0, 8),
                'expires_at'    => $expiresAtText ?? Carbon::now()->addHours(12)->format('Y-m-d H:i:s'),
            ],
        ];
    }
}
