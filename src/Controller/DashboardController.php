<?php

namespace App\Controller;

use App\Entity\Link;
use App\Entity\User;
use App\Repository\LinkRepository;
use App\Repository\VisitRepository;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        EntityManagerInterface $em,
        SlugGenerator $slugGenerator,
        ValidatorInterface $validator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $targetUrl = trim((string) $request->request->get('target_url', ''));

        $label = trim((string) $request->request->get('label', ''));

        $link = new Link();
        $link->setUser($user);
        $link->setTargetUrl($targetUrl);
        $link->setSlug($slugGenerator->generate());
        if ($label !== '') {
            $link->setLabel($label);
        }

        $errors = $validator->validate($link);
        if (count($errors) > 0) {
            $this->addFlash('error', 'Invalid URL. Please enter a valid URL including http:// or https://');
            return $this->redirectToRoute('app_dashboard');
        }

        $em->persist($link);
        $em->flush();

        $this->addFlash('success', 'Link created successfully!');
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
        $link = $linkRepository->find($id);

        if (!$link || !$link->getUser()->getId()->equals($user->getId())) {
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
        $visit = $visitRepository->find($id);

        if (!$visit || !$visit->getLink()->getUser()->getId()->equals($user->getId())) {
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
        $link = $linkRepository->find($id);

        if (!$link || !$link->getUser()->getId()->equals($user->getId())) {
            throw $this->createNotFoundException('Link not found.');
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
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $link = $linkRepository->find($id);

        if (!$link || !$link->getUser()->getId()->equals($user->getId())) {
            return new JsonResponse(['error' => 'Link not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $label = trim((string) ($data['label'] ?? ''));
        $link->setLabel($label === '' ? null : $label);

        $errors = $validator->validate($link);
        if (count($errors) > 0) {
            return new JsonResponse(['error' => (string) $errors->get(0)->getMessage()], 422);
        }

        $em->flush();

        return new JsonResponse(['label' => $link->getLabel()]);
    }
}
