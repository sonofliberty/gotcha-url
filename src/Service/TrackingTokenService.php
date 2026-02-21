<?php

namespace App\Service;

class TrackingTokenService
{
    private const TTL_SECONDS = 60;

    public function __construct(
        private string $secret,
    ) {
    }

    /**
     * @return array{token: string, ts: int}
     */
    public function generate(string $slug, string $clientIp): array
    {
        $ts = time();
        $token = $this->computeHmac($slug, $clientIp, $ts);

        return ['token' => $token, 'ts' => $ts];
    }

    public function verify(string $slug, string $clientIp, string $token, int $ts): bool
    {
        if (abs(time() - $ts) > self::TTL_SECONDS) {
            return false;
        }

        $expected = $this->computeHmac($slug, $clientIp, $ts);

        return hash_equals($expected, $token);
    }

    private function computeHmac(string $slug, string $clientIp, int $ts): string
    {
        $payload = implode('|', [$slug, $clientIp, $ts]);

        return hash_hmac('sha256', $payload, $this->secret);
    }
}
