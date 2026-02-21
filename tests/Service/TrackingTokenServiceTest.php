<?php

namespace App\Tests\Service;

use App\Service\TrackingTokenService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TrackingTokenServiceTest extends TestCase
{
    private const SECRET = 'test-secret-key-for-hmac';

    private TrackingTokenService $service;

    protected function setUp(): void
    {
        $this->service = new TrackingTokenService(self::SECRET);
    }

    #[Test]
    public function generateReturnsTokenAndTimestamp(): void
    {
        $result = $this->service->generate('abcdefg', '127.0.0.1');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('ts', $result);
        $this->assertIsString($result['token']);
        $this->assertIsInt($result['ts']);
        $this->assertEqualsWithDelta(time(), $result['ts'], 1);
    }

    #[Test]
    public function generateProducesHexString(): void
    {
        $result = $this->service->generate('abcdefg', '127.0.0.1');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['token']);
    }

    #[Test]
    public function verifyAcceptsValidToken(): void
    {
        $result = $this->service->generate('abcdefg', '127.0.0.1');

        $this->assertTrue(
            $this->service->verify('abcdefg', '127.0.0.1', $result['token'], $result['ts'])
        );
    }

    #[Test]
    public function verifyRejectsExpiredToken(): void
    {
        $slug = 'abcdefg';
        $ip = '127.0.0.1';
        $ts = time() - 120; // well past the 60s TTL
        $token = hash_hmac('sha256', implode('|', [$slug, $ip, $ts]), self::SECRET);

        $this->assertFalse($this->service->verify($slug, $ip, $token, $ts));
    }

    #[Test]
    public function verifyRejectsWrongSlug(): void
    {
        $result = $this->service->generate('aaaaaaa', '127.0.0.1');

        $this->assertFalse(
            $this->service->verify('bbbbbbb', '127.0.0.1', $result['token'], $result['ts'])
        );
    }

    #[Test]
    public function verifyRejectsWrongIp(): void
    {
        $result = $this->service->generate('abcdefg', '1.1.1.1');

        $this->assertFalse(
            $this->service->verify('abcdefg', '2.2.2.2', $result['token'], $result['ts'])
        );
    }

    #[Test]
    public function verifyRejectsTamperedToken(): void
    {
        $result = $this->service->generate('abcdefg', '127.0.0.1');

        // Flip the first character
        $tampered = $result['token'];
        $tampered[0] = $tampered[0] === 'a' ? 'b' : 'a';

        $this->assertFalse(
            $this->service->verify('abcdefg', '127.0.0.1', $tampered, $result['ts'])
        );
    }

    #[Test]
    public function differentSecretsProduceDifferentTokens(): void
    {
        $other = new TrackingTokenService('different-secret');

        $result1 = $this->service->generate('abcdefg', '127.0.0.1');
        // Manually compute with same ts so we compare apples to apples
        $token2 = hash_hmac(
            'sha256',
            implode('|', ['abcdefg', '127.0.0.1', $result1['ts']]),
            'different-secret'
        );

        $this->assertNotSame($result1['token'], $token2);
    }
}
