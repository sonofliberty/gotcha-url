<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\ApiTokenAuthenticator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticatorTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueForBearerHeader(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $request = Request::create('/api/v1/links');
        $request->headers->set('Authorization', 'Bearer xyz');

        $this->assertTrue($auth->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseForOtherHeaders(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $request = Request::create('/api/v1/links');
        $request->headers->set('Authorization', 'Basic abc');

        $this->assertFalse($auth->supports($request));
    }

    #[Test]
    public function supportsReturnsFalseWithoutAuthHeader(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $request = Request::create('/api/v1/links');

        $this->assertFalse($auth->supports($request));
    }

    #[Test]
    public function authenticateReturnsPassportWithExtractedToken(): void
    {
        $user = (new User())->setAccountCode('the-token');
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())
            ->method('findByAccountCode')
            ->with('the-token')
            ->willReturn($user);

        $auth = new ApiTokenAuthenticator($repo);

        $request = Request::create('/api/v1/links');
        $request->headers->set('Authorization', 'Bearer the-token');

        $passport = $auth->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);
        $this->assertSame('the-token', $badge->getUserIdentifier());
        $this->assertSame($user, ($badge->getUserLoader())('the-token'));
    }

    #[Test]
    public function authenticateThrowsWhenTokenEmpty(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $request = Request::create('/api/v1/links');
        $request->headers->set('Authorization', 'Bearer ');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $auth->authenticate($request);
    }

    #[Test]
    public function userLoaderThrowsWhenAccountCodeUnknown(): void
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByAccountCode')->willReturn(null);

        $auth = new ApiTokenAuthenticator($repo);

        $request = Request::create('/api/v1/links');
        $request->headers->set('Authorization', 'Bearer wrong');

        $passport = $auth->authenticate($request);
        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);

        $this->expectException(UserNotFoundException::class);
        ($badge->getUserLoader())('wrong');
    }

    #[Test]
    public function startReturns401Json(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $response = $auth->start(Request::create('/api/v1/links'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Bearer realm="api"', (string) $response->headers->get('WWW-Authenticate'));
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('unauthorized', $body['error']);
    }

    #[Test]
    public function authenticationFailureReturns401Json(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $response = $auth->onAuthenticationFailure(
            Request::create('/api/v1/links'),
            new AuthenticationException('bad'),
        );

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function authenticationSuccessReturnsNullToContinue(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $token = $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
        $response = $auth->onAuthenticationSuccess(Request::create('/api/v1/links'), $token, 'api');

        $this->assertNull($response);
    }

    #[Test]
    public function bearerTokenIsTrimmed(): void
    {
        $auth = new ApiTokenAuthenticator($this->createStub(UserRepository::class));

        $request = Request::create('/api/v1/links');
        $request->headers->set('Authorization', 'Bearer   clean-token   ');

        $passport = $auth->authenticate($request);
        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);

        $this->assertSame('clean-token', $badge->getUserIdentifier());
    }
}
