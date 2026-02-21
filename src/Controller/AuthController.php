<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        // If already authenticated, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $request->query->has('error');

        return $this->render('auth/login.html.twig', [
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function register(EntityManagerInterface $em): Response
    {
        $user = new User();
        $user->setAccountCode(Uuid::v4()->toRfc4122());

        $em->persist($user);
        $em->flush();

        $response = new RedirectResponse($this->generateUrl('app_dashboard'));
        $response->headers->setCookie(
            Cookie::create('gotcha_account')
                ->withValue($user->getAccountCode())
                ->withExpires(new \DateTimeImmutable('+1 year'))
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSameSite('lax')
        );

        return $response;
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        // Handled by security system
        throw new \LogicException('This should never be reached.');
    }
}
