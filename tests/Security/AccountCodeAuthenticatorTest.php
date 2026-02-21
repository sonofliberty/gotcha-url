<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\AccountCodeAuthenticator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccountCodeAuthenticatorTest extends TestCase
{
    private AccountCodeAuthenticator $authenticator;

    protected function setUp(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $this->authenticator = new AccountCodeAuthenticator($userRepo);
    }

    #[Test]
    public function supportsPostLogin(): void
    {
        $request = Request::create('/login', 'POST', ['account_code' => 'abc']);

        $this->assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function supportsCookiePresent(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set('gotcha_account', 'some-code');

        $this->assertTrue($this->authenticator->supports($request));
    }

    #[Test]
    public function supportsNoCookieNoLogin(): void
    {
        $request = Request::create('/dashboard', 'GET');

        $this->assertFalse($this->authenticator->supports($request));
    }

    #[Test]
    public function authenticateFromPost(): void
    {
        $user = new User();
        $user->setAccountCode('post-code');

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findByAccountCode')->willReturn($user);
        $authenticator = new AccountCodeAuthenticator($userRepo);

        $request = Request::create('/login', 'POST', ['account_code' => 'post-code']);
        $passport = $authenticator->authenticate($request);

        $this->assertSame('post-code', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    #[Test]
    public function authenticateFromCookie(): void
    {
        $user = new User();
        $user->setAccountCode('cookie-code');

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findByAccountCode')->willReturn($user);
        $authenticator = new AccountCodeAuthenticator($userRepo);

        $request = Request::create('/dashboard', 'GET');
        $request->cookies->set('gotcha_account', 'cookie-code');

        $passport = $authenticator->authenticate($request);

        $this->assertSame('cookie-code', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    #[Test]
    public function authenticateThrowsOnEmptyCode(): void
    {
        $request = Request::create('/login', 'POST', ['account_code' => '']);

        $this->expectException(AuthenticationException::class);
        $this->authenticator->authenticate($request);
    }

    #[Test]
    public function successSetsCookieForPost(): void
    {
        $user = new User();
        $user->setAccountCode('success-code');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = Request::create('/login', 'POST');

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertNotNull($response);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/dashboard', $response->headers->get('Location'));

        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $cookie = $cookies[0];
        $this->assertSame('gotcha_account', $cookie->getName());
        $this->assertSame('success-code', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(time() + 364 * 86400, $cookie->getExpiresTime());
    }

    #[Test]
    public function successReturnsNullForNonPost(): void
    {
        $user = new User();
        $user->setAccountCode('cookie-code');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $request = Request::create('/dashboard', 'GET');

        $response = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertNull($response);
    }

    #[Test]
    public function failureRedirectsWithErrorForPost(): void
    {
        $request = Request::create('/login', 'POST');
        $exception = new AuthenticationException('Bad code');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login?error=1', $response->headers->get('Location'));
    }

    #[Test]
    public function failureClearsCookieForNonPost(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $exception = new AuthenticationException('Bad cookie');

        $response = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('Location'));

        $cookies = $response->headers->getCookies();
        $cleared = array_filter($cookies, fn($c) => $c->getName() === 'gotcha_account');
        $this->assertNotEmpty($cleared);
        $cookie = reset($cleared);
        $this->assertTrue($cookie->isCleared());
    }

    #[Test]
    public function startRedirectsToLogin(): void
    {
        $request = Request::create('/dashboard', 'GET');

        $response = $this->authenticator->start($request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->headers->get('Location'));
    }
}
