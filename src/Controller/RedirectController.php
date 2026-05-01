<?php

namespace App\Controller;

use App\Repository\LinkRepository;
use App\Service\PageResponseFactory;
use App\Service\TrackingTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RedirectController extends AbstractController
{
    #[Route('/{slug}', name: 'app_track', requirements: ['slug' => '[a-zA-Z0-9]{7}'], priority: -100)]
    public function track(string $slug, Request $request, LinkRepository $linkRepository, TrackingTokenService $tokenService, PageResponseFactory $pageResponseFactory): Response
    {
        $link = $linkRepository->findBySlug($slug);

        if (!$link) {
            throw $this->createNotFoundException('Link not found.');
        }

        if (!$link->isTrackingEnabled() && $link->isRedirect()) {
            return $this->redirect($link->getTargetUrl());
        }

        $tokenData = $link->isTrackingEnabled()
            ? $tokenService->generate($slug, $request->getClientIp() ?? '0.0.0.0')
            : ['token' => null, 'ts' => null];

        if ($link->isPage()) {
            return $pageResponseFactory->build($link, $tokenData['token'], $tokenData['ts']);
        }

        return $this->render('redirect/loading.html.twig', [
            'slug' => $slug,
            'targetUrl' => $link->getTargetUrl(),
            'trackToken' => $tokenData['token'],
            'trackTs' => $tokenData['ts'],
        ]);
    }
}
