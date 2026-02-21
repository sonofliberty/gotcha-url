<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AccountCodeAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const COOKIE_NAME = 'gotcha_account';

    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        if ($request->getPathInfo() === '/login' && $request->isMethod('POST')) {
            return true;
        }

        return $request->cookies->has(self::COOKIE_NAME);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $accountCode = '';

        if ($request->getPathInfo() === '/login' && $request->isMethod('POST')) {
            $accountCode = trim((string) $request->request->get('account_code', ''));
        } elseif ($request->cookies->has(self::COOKIE_NAME)) {
            $accountCode = (string) $request->cookies->get(self::COOKIE_NAME);
        }

        if ($accountCode === '') {
            throw new AuthenticationException('No account code provided.');
        }

        return new SelfValidatingPassport(
            new UserBadge($accountCode, function (string $identifier) {
                $user = $this->userRepository->findByAccountCode($identifier);
                if ($user === null) {
                    throw new UserNotFoundException();
                }
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $accountCode = $token->getUser()->getUserIdentifier();

        if ($request->getPathInfo() === '/login' && $request->isMethod('POST')) {
            $response = new RedirectResponse('/dashboard');
            $response->headers->setCookie(
                Cookie::create(self::COOKIE_NAME)
                    ->withValue($accountCode)
                    ->withExpires(new \DateTimeImmutable('+1 year'))
                    ->withPath('/')
                    ->withHttpOnly(true)
                    ->withSameSite('lax')
            );

            return $response;
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->getPathInfo() === '/login' && $request->isMethod('POST')) {
            return new RedirectResponse('/login?error=1');
        }

        $response = new RedirectResponse('/login');
        $response->headers->clearCookie(self::COOKIE_NAME, '/');

        return $response;
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse('/login');
    }
}
