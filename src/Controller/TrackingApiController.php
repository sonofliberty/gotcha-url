<?php

namespace App\Controller;

use App\Entity\Visit;
use App\Repository\LinkRepository;
use App\Service\TrackingTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class TrackingApiController extends AbstractController
{
    #[Route('/api/track', name: 'api_track', methods: ['POST'])]
    public function collect(
        Request $request,
        LinkRepository $linkRepository,
        EntityManagerInterface $em,
        RateLimiterFactory $trackingApiLimiter,
        TrackingTokenService $tokenService,
    ): JsonResponse {
        // Layer 1: Rate limiting (cheapest check first)
        $limiter = $trackingApiLimiter->create($request->getClientIp() ?? '0.0.0.0');
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            return new JsonResponse(
                ['error' => 'Too many requests'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => $retryAfter->getTimestamp() - time()],
            );
        }

        // Parse JSON + validate slug
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || empty($data['slug'])) {
            return new JsonResponse(['error' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        // Layer 2: HMAC token verification
        $token = $data['token'] ?? '';
        $ts = (int) ($data['ts'] ?? 0);

        if (!$tokenService->verify($data['slug'], $request->getClientIp() ?? '0.0.0.0', $token, $ts)) {
            return new JsonResponse(['error' => 'Invalid or expired token'], Response::HTTP_FORBIDDEN);
        }

        $link = $linkRepository->findBySlug($data['slug']);
        if (!$link) {
            return new JsonResponse(['error' => 'Link not found'], Response::HTTP_NOT_FOUND);
        }

        $visit = new Visit();
        $visit->setLink($link);
        $visit->setIpAddress($request->getClientIp() ?? '0.0.0.0');
        $visit->setUserAgent(mb_substr((string) $request->headers->get('User-Agent', ''), 0, 512) ?: null);
        $visit->setReferrer(mb_substr((string) ($data['referrer'] ?? ''), 0, 2048) ?: null);
        $visit->setScreenResolution(mb_substr((string) ($data['screenResolution'] ?? ''), 0, 20) ?: null);
        $visit->setTimezone(mb_substr((string) ($data['timezone'] ?? ''), 0, 64) ?: null);
        $visit->setLanguage(mb_substr((string) ($data['language'] ?? ''), 0, 10) ?: null);
        $visit->setPlatform(mb_substr((string) ($data['platform'] ?? ''), 0, 64) ?: null);
        $visit->setCookiesEnabled(isset($data['cookiesEnabled']) ? (bool) $data['cookiesEnabled'] : null);
        $visit->setCountryCode(mb_substr((string) $request->headers->get('CF-IPCountry', ''), 0, 2) ?: null);
        $visit->setCity(mb_substr((string) $request->headers->get('CF-IPCity', ''), 0, 128) ?: null);
        $visit->setDevicePixelRatio(mb_substr((string) ($data['devicePixelRatio'] ?? ''), 0, 10) ?: null);
        $visit->setColorDepth(isset($data['colorDepth']) ? (int) $data['colorDepth'] : null);
        $visit->setTouchSupport(isset($data['touchSupport']) ? (bool) $data['touchSupport'] : null);
        $visit->setMaxTouchPoints(isset($data['maxTouchPoints']) ? (int) $data['maxTouchPoints'] : null);
        $visit->setHardwareConcurrency(isset($data['hardwareConcurrency']) ? (int) $data['hardwareConcurrency'] : null);
        $visit->setDeviceMemory(mb_substr((string) ($data['deviceMemory'] ?? ''), 0, 10) ?: null);
        $visit->setConnectionType(mb_substr((string) ($data['connectionType'] ?? ''), 0, 20) ?: null);
        $visit->setDoNotTrack(isset($data['doNotTrack']) ? (bool) $data['doNotTrack'] : null);
        $visit->setViewportWidth(isset($data['viewportWidth']) ? (int) $data['viewportWidth'] : null);
        $visit->setViewportHeight(isset($data['viewportHeight']) ? (int) $data['viewportHeight'] : null);
        $visit->setVendor(mb_substr((string) ($data['vendor'] ?? ''), 0, 64) ?: null);
        $visit->setPdfViewerEnabled(isset($data['pdfViewerEnabled']) ? (bool) $data['pdfViewerEnabled'] : null);
        $visit->setWebglRenderer(mb_substr((string) ($data['webglRenderer'] ?? ''), 0, 256) ?: null);

        $em->persist($visit);
        $em->flush();

        return new JsonResponse(['redirect' => $link->getTargetUrl()]);
    }
}
