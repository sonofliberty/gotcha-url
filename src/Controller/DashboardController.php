<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\LinkRepository;
use App\Repository\VisitRepository;
use App\Service\LinkCreator;
use App\Service\LinkUpdateInput;
use App\Service\LinkUpdater;
use App\Service\PageResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\ConstraintViolationListInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request, LinkRepository $linkRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $links = $linkRepository->findByUserPaginated($user, $page);
        $totalPages = max(1, (int) ceil(count($links) / 20));

        return $this->render('dashboard/index.html.twig', [
            'links' => $links,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/dashboard/links', name: 'app_create_link', methods: ['POST'])]
    public function createLink(
        Request $request,
        LinkCreator $linkCreator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $type = trim((string) $request->request->get('link_type', 'redirect'));
        $targetUrl = trim((string) $request->request->get('target_url', ''));
        $markdownContent = trim((string) $request->request->get('markdown_content', ''));
        $label = trim((string) $request->request->get('label', ''));

        $result = $linkCreator->create(
            user: $user,
            type: $type,
            targetUrl: $targetUrl !== '' ? $targetUrl : null,
            markdownContent: $markdownContent !== '' ? $markdownContent : null,
            label: $label !== '' ? $label : null,
            customSlug: null,
        );

        if ($result instanceof ConstraintViolationListInterface) {
            $this->addFlash('error', (string) $result->get(0)->getMessage());
            return $this->redirectToRoute('app_dashboard');
        }

        $this->addFlash('success', $result->isPage() ? 'Content page created!' : 'Link created successfully!');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/links/{id}', name: 'app_link_detail', methods: ['GET'])]
    public function linkDetail(
        string $id,
        Request $request,
        LinkRepository $linkRepository,
        VisitRepository $visitRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $link = $linkRepository->findOneByIdAndUser($id, $user);

        if (!$link) {
            throw $this->createNotFoundException('Link not found.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $visits = $visitRepository->findByLinkPaginated($link, $page);
        $totalPages = max(1, (int) ceil(count($visits) / 50));

        return $this->render('dashboard/link_detail.html.twig', [
            'link' => $link,
            'visits' => $visits,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/dashboard/visits/{id}', name: 'app_visit_detail', methods: ['GET'])]
    public function visitDetail(
        string $id,
        VisitRepository $visitRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $visit = $visitRepository->findOneByIdAndUser($id, $user);

        if (!$visit) {
            throw $this->createNotFoundException('Visit not found.');
        }

        return $this->render('dashboard/visit_detail.html.twig', [
            'visit' => $visit,
        ]);
    }

    #[Route('/dashboard/links/{id}', name: 'app_delete_link', methods: ['DELETE'])]
    public function deleteLink(
        string $id,
        Request $request,
        LinkRepository $linkRepository,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $link = $linkRepository->findOneByIdAndUser($id, $user);

        if (!$link) {
            throw $this->createNotFoundException('Link not found.');
        }

        if (!$this->isCsrfTokenValid('delete-link-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $em->remove($link);
        $em->flush();

        $this->addFlash('success', 'Link deleted.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/links/{id}/label', name: 'app_update_label', methods: ['PATCH'])]
    public function updateLabel(
        string $id,
        Request $request,
        LinkRepository $linkRepository,
        LinkUpdater $linkUpdater,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $link = $linkRepository->findOneByIdAndUser($id, $user);

        if (!$link) {
            return new JsonResponse(['error' => 'Link not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $label = trim((string) ($data['label'] ?? ''));

        $result = $linkUpdater->update($link, LinkUpdateInput::label($label));
        if ($result instanceof ConstraintViolationListInterface) {
            return new JsonResponse(['error' => (string) $result->get(0)->getMessage()], 422);
        }

        return new JsonResponse(['label' => $link->getLabel()]);
    }

    #[Route('/dashboard/links/{id}/tracking', name: 'app_update_tracking', methods: ['PATCH'])]
    public function updateTracking(
        string $id,
        Request $request,
        LinkRepository $linkRepository,
        LinkUpdater $linkUpdater,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $link = $linkRepository->findOneByIdAndUser($id, $user);

        if (!$link) {
            return new JsonResponse(['error' => 'Link not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $trackingEnabled = (bool) ($data['tracking_enabled'] ?? true);

        $result = $linkUpdater->update($link, LinkUpdateInput::trackingEnabled($trackingEnabled));
        if ($result instanceof ConstraintViolationListInterface) {
            return new JsonResponse(['error' => (string) $result->get(0)->getMessage()], 422);
        }

        return new JsonResponse(['tracking_enabled' => $link->isTrackingEnabled()]);
    }

    #[Route('/dashboard/links/{id}/content', name: 'app_update_content', methods: ['PATCH'])]
    public function editContent(
        string $id,
        Request $request,
        LinkRepository $linkRepository,
        LinkUpdater $linkUpdater,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $link = $linkRepository->findOneByIdAndUser($id, $user);

        if (!$link) {
            return new JsonResponse(['error' => 'Link not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim((string) ($data['markdown_content'] ?? ''));

        $result = $linkUpdater->update($link, LinkUpdateInput::markdownContent($content));
        if ($result instanceof ConstraintViolationListInterface) {
            return new JsonResponse(['error' => (string) $result->get(0)->getMessage()], 422);
        }

        return new JsonResponse(['markdown_content' => $link->getMarkdownContent()]);
    }

    #[Route('/dashboard/links/{id}/preview', name: 'app_link_preview', methods: ['GET'])]
    public function previewContent(
        string $id,
        LinkRepository $linkRepository,
        PageResponseFactory $pageResponseFactory,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $link = $linkRepository->findOneByIdAndUser($id, $user);

        if (!$link) {
            throw $this->createNotFoundException('Link not found.');
        }

        if (!$link->isPage()) {
            throw $this->createNotFoundException('Preview is only available for page links.');
        }

        return $pageResponseFactory->build($link, null, null, 'dashboard/link_preview.html.twig');
    }
}
