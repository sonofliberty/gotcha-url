<?php

namespace App\Tests\Controller;

use App\Controller\TrackingApiController;
use App\Entity\Link;
use App\Entity\Visit;
use App\Repository\LinkRepository;
use App\Service\TrackingTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class TrackingApiControllerTest extends TestCase
{
    private TrackingApiController $controller;
    private LinkRepository $linkRepo;
    private EntityManagerInterface $em;
    private TrackingTokenService $tokenService;

    protected function setUp(): void
    {
        $this->controller = new TrackingApiController();
        $this->linkRepo = $this->createStub(LinkRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->tokenService = $this->createStub(TrackingTokenService::class);
    }

    #[Test]
    public function rateLimitExceededReturns429(): void
    {
        $limiterFactory = $this->createRejectedRateLimiter();

        $request = Request::create('/api/track', 'POST', [], [], [], [], json_encode(['slug' => 'abcdefg']));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->collect($request, $this->linkRepo, $this->em, $limiterFactory, $this->tokenService);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    #[Test]
    public function missingSlugReturns400(): void
    {
        $limiterFactory = $this->createAcceptedRateLimiter();

        $request = Request::create('/api/track', 'POST', [], [], [], [], json_encode([]));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->collect($request, $this->linkRepo, $this->em, $limiterFactory, $this->tokenService);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function invalidTokenReturns403(): void
    {
        $limiterFactory = $this->createAcceptedRateLimiter();

        $this->tokenService->method('verify')->willReturn(false);

        $request = Request::create('/api/track', 'POST', [], [], [], [], json_encode([
            'slug' => 'abcdefg',
            'token' => 'bad-token',
            'ts' => time(),
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->collect($request, $this->linkRepo, $this->em, $limiterFactory, $this->tokenService);

        $this->assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function nonExistentLinkReturns404(): void
    {
        $limiterFactory = $this->createAcceptedRateLimiter();

        $this->tokenService->method('verify')->willReturn(true);
        $this->linkRepo->method('findBySlug')->willReturn(null);

        $request = Request::create('/api/track', 'POST', [], [], [], [], json_encode([
            'slug' => 'abcdefg',
            'token' => 'valid',
            'ts' => time(),
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->collect($request, $this->linkRepo, $this->em, $limiterFactory, $this->tokenService);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function successfulTrackingReturns200(): void
    {
        $limiterFactory = $this->createAcceptedRateLimiter();

        $this->tokenService->method('verify')->willReturn(true);

        $link = $this->createStub(Link::class);
        $link->method('getTargetUrl')->willReturn('https://example.com');
        $this->linkRepo->method('findBySlug')->willReturn($link);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Visit::class));
        $em->expects($this->once())->method('flush');

        $request = Request::create('/api/track', 'POST', [], [], [], [
            'REMOTE_ADDR' => '1.2.3.4',
            'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        ], json_encode([
            'slug' => 'abcdefg',
            'token' => 'valid',
            'ts' => time(),
            'referrer' => 'https://google.com',
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller->collect($request, $this->linkRepo, $em, $limiterFactory, $this->tokenService);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('https://example.com', $data['redirect']);
    }

    #[Test]
    public function visitFieldsPopulated(): void
    {
        $limiterFactory = $this->createAcceptedRateLimiter();

        $this->tokenService->method('verify')->willReturn(true);

        $link = $this->createStub(Link::class);
        $link->method('getTargetUrl')->willReturn('https://example.com');
        $this->linkRepo->method('findBySlug')->willReturn($link);

        $capturedVisit = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Visit $visit) use (&$capturedVisit) {
                $capturedVisit = $visit;
                return true;
            }));

        $request = Request::create('/api/track', 'POST', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ], json_encode([
            'slug' => 'abcdefg',
            'token' => 'valid',
            'ts' => time(),
            'referrer' => 'https://referrer.com',
            'screenResolution' => '1920x1080',
            'timezone' => 'America/New_York',
            'language' => 'en-US',
            'platform' => 'Win32',
            'cookiesEnabled' => true,
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $this->controller->collect($request, $this->linkRepo, $em, $limiterFactory, $this->tokenService);

        $this->assertNotNull($capturedVisit);
        $this->assertSame('10.0.0.1', $capturedVisit->getIpAddress());
        $this->assertSame('Mozilla/5.0', $capturedVisit->getUserAgent());
        $this->assertSame('https://referrer.com', $capturedVisit->getReferrer());
        $this->assertSame('1920x1080', $capturedVisit->getScreenResolution());
        $this->assertSame('America/New_York', $capturedVisit->getTimezone());
        $this->assertSame('en-US', $capturedVisit->getLanguage());
        $this->assertSame('Win32', $capturedVisit->getPlatform());
        $this->assertTrue($capturedVisit->getCookiesEnabled());
    }

    #[Test]
    public function userAgentTruncatedTo512(): void
    {
        $limiterFactory = $this->createAcceptedRateLimiter();

        $this->tokenService->method('verify')->willReturn(true);

        $link = $this->createStub(Link::class);
        $link->method('getTargetUrl')->willReturn('https://example.com');
        $this->linkRepo->method('findBySlug')->willReturn($link);

        $capturedVisit = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Visit $visit) use (&$capturedVisit) {
                $capturedVisit = $visit;
                return true;
            }));

        $longUa = str_repeat('A', 1000);
        $request = Request::create('/api/track', 'POST', [], [], [], [
            'REMOTE_ADDR' => '1.2.3.4',
            'HTTP_USER_AGENT' => $longUa,
        ], json_encode([
            'slug' => 'abcdefg',
            'token' => 'valid',
            'ts' => time(),
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $this->controller->collect($request, $this->linkRepo, $em, $limiterFactory, $this->tokenService);

        $this->assertNotNull($capturedVisit);
        $this->assertSame(512, mb_strlen($capturedVisit->getUserAgent()));
    }

    #[Test]
    public function cloudflareGeoHeadersExtracted(): void
    {
        $limiterFactory = $this->createAcceptedRateLimiter();

        $this->tokenService->method('verify')->willReturn(true);

        $link = $this->createStub(Link::class);
        $link->method('getTargetUrl')->willReturn('https://example.com');
        $this->linkRepo->method('findBySlug')->willReturn($link);

        $capturedVisit = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Visit $visit) use (&$capturedVisit) {
                $capturedVisit = $visit;
                return true;
            }));

        $request = Request::create('/api/track', 'POST', [], [], [], [
            'REMOTE_ADDR' => '1.2.3.4',
            'HTTP_CF_IPCOUNTRY' => 'US',
            'HTTP_CF_IPCITY' => 'San Francisco',
        ], json_encode([
            'slug' => 'abcdefg',
            'token' => 'valid',
            'ts' => time(),
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $this->controller->collect($request, $this->linkRepo, $em, $limiterFactory, $this->tokenService);

        $this->assertNotNull($capturedVisit);
        $this->assertSame('US', $capturedVisit->getCountryCode());
        $this->assertSame('San Francisco', $capturedVisit->getCity());
    }

    private function createAcceptedRateLimiter(): RateLimiterFactory
    {
        return new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'],
            new InMemoryStorage()
        );
    }

    private function createRejectedRateLimiter(): RateLimiterFactory
    {
        $storage = new InMemoryStorage();
        $factory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 hour'],
            $storage
        );

        // Exhaust the limit for the default IP (127.0.0.1 from Request::create)
        $factory->create('127.0.0.1')->consume();

        return $factory;
    }
}
