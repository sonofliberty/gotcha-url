<?php

namespace App\Tests\EventListener;

use App\Entity\User;
use App\EventListener\ApiRateLimitListener;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ApiRateLimitListenerTest extends TestCase
{
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    #[Test]
    public function ignoresPathsOutsideApiV1(): void
    {
        $listener = new ApiRateLimitListener(
            $this->createRejectedLimiter(),
            $this->tokenStorageWith($this->user('any')),
        );

        $event = $this->event('/api/doc');
        $listener($event);

        $this->assertFalse($event->hasResponse());
    }

    #[Test]
    public function ignoresWhenNoUserAuthenticated(): void
    {
        $listener = new ApiRateLimitListener(
            $this->createRejectedLimiter(),
            $this->tokenStorageWith(null),
        );

        $event = $this->event('/api/v1/links');
        $listener($event);

        $this->assertFalse($event->hasResponse());
    }

    #[Test]
    public function passesThroughWhenLimiterAcceptsRequest(): void
    {
        $listener = new ApiRateLimitListener(
            $this->createAcceptedLimiter(),
            $this->tokenStorageWith($this->user('alice')),
        );

        $event = $this->event('/api/v1/links');
        $listener($event);

        $this->assertFalse($event->hasResponse());
    }

    #[Test]
    public function returns429WhenLimiterRejectsRequest(): void
    {
        $listener = new ApiRateLimitListener(
            $this->createRejectedLimiter('alice'),
            $this->tokenStorageWith($this->user('alice')),
        );

        $event = $this->event('/api/v1/links');
        $listener($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertSame(429, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Retry-After'));
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('rate_limited', $body['error']);
        $this->assertSame('Too many requests.', $body['message']);
        $this->assertIsInt($body['retry_after']);
    }

    #[Test]
    public function ignoresSubRequests(): void
    {
        $listener = new ApiRateLimitListener(
            $this->createRejectedLimiter(),
            $this->tokenStorageWith($this->user('alice')),
        );

        $event = new RequestEvent(
            $this->kernel,
            Request::create('/api/v1/links'),
            HttpKernelInterface::SUB_REQUEST,
        );
        $listener($event);

        $this->assertFalse($event->hasResponse());
    }

    #[Test]
    public function differentUsersConsumeIndependentBuckets(): void
    {
        $factory = new RateLimiterFactory(
            ['id' => 'api', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );

        $aliceListener = new ApiRateLimitListener($factory, $this->tokenStorageWith($this->user('alice')));
        $bobListener = new ApiRateLimitListener($factory, $this->tokenStorageWith($this->user('bob')));

        $first = $this->event('/api/v1/links');
        $aliceListener($first);
        $this->assertFalse($first->hasResponse(), 'alice first request should pass');

        $second = $this->event('/api/v1/links');
        $aliceListener($second);
        $this->assertTrue($second->hasResponse(), 'alice second request should be limited');

        $third = $this->event('/api/v1/links');
        $bobListener($third);
        $this->assertFalse($third->hasResponse(), 'bob first request unaffected by alice');
    }

    private function event(string $path): RequestEvent
    {
        return new RequestEvent(
            $this->kernel,
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function user(string $accountCode): User
    {
        return (new User())->setAccountCode($accountCode);
    }

    private function tokenStorageWith(?User $user): TokenStorageInterface
    {
        $storage = $this->createStub(TokenStorageInterface::class);
        if ($user === null) {
            $storage->method('getToken')->willReturn(null);
            return $storage;
        }

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $storage->method('getToken')->willReturn($token);
        return $storage;
    }

    private function createAcceptedLimiter(): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'api', 'policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
    }

    private function createRejectedLimiter(string $accountCode = 'any'): RateLimiterFactory
    {
        $factory = new RateLimiterFactory(
            ['id' => 'api', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            new InMemoryStorage(),
        );
        $factory->create($accountCode)->consume();
        return $factory;
    }
}
