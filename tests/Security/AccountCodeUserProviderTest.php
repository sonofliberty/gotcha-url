<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\AccountCodeUserProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

class AccountCodeUserProviderTest extends TestCase
{
    #[Test]
    public function loadUserReturnsUserWhenFound(): void
    {
        $user = new User();
        $user->setAccountCode('test-code-123');

        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByAccountCode')->willReturn($user);

        $provider = new AccountCodeUserProvider($repo);
        $result = $provider->loadUserByIdentifier('test-code-123');

        $this->assertSame($user, $result);
    }

    #[Test]
    public function loadUserThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findByAccountCode')->willReturn(null);

        $provider = new AccountCodeUserProvider($repo);

        try {
            $provider->loadUserByIdentifier('nonexistent');
            $this->fail('Expected UserNotFoundException');
        } catch (UserNotFoundException $e) {
            $this->assertSame('nonexistent', $e->getUserIdentifier());
        }
    }

    #[Test]
    public function supportsClassTrueForUser(): void
    {
        $repo = $this->createStub(UserRepository::class);
        $provider = new AccountCodeUserProvider($repo);

        $this->assertTrue($provider->supportsClass(User::class));
    }

    #[Test]
    public function supportsClassFalseForOther(): void
    {
        $repo = $this->createStub(UserRepository::class);
        $provider = new AccountCodeUserProvider($repo);

        $this->assertFalse($provider->supportsClass(\stdClass::class));
    }

    #[Test]
    public function refreshUserDelegates(): void
    {
        $user = new User();
        $user->setAccountCode('refresh-code');

        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())
            ->method('findByAccountCode')
            ->with('refresh-code')
            ->willReturn($user);

        $provider = new AccountCodeUserProvider($repo);
        $result = $provider->refreshUser($user);

        $this->assertSame($user, $result);
    }
}
