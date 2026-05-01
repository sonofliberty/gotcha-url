<?php

namespace App\Controller\Api;

use App\ApiResource\ErrorResponse;
use App\ApiResource\PaginatedVisits;
use App\ApiResource\RateLimitErrorResponse;
use App\ApiResource\VisitResource;
use App\Entity\User;
use App\Repository\LinkRepository;
use App\Repository\VisitRepository;
use App\Service\Api\ApiResponder;
use App\Service\VisitSerializer;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(
    name: 'Visits',
    description: 'Read recorded visits to links you own. All endpoints require Bearer authentication and share the per-token rate limit of 60 requests per minute (sliding window). Lists are paginated 50 per page.',
)]
class VisitsApiController extends AbstractController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly VisitSerializer $serializer,
        private readonly ApiResponder $responder,
    ) {
    }

    #[Route('/api/v1/links/{id}/visits', name: 'api_v1_link_visits_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List visits for a link',
        description: 'Returns visits recorded for the link with the given id, newest first, paginated 50 per page. The link must belong to the authenticated user; otherwise a 404 is returned with no enumeration leak.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Link UUID (v7).',
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '01933b8a-e234-7000-8a00-000000000001',
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: '1-indexed page number. Out-of-range values return an empty `data` array with the requested `page` echoed back.',
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1),
                example: 1,
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of visits.',
                content: new OA\JsonContent(ref: new Model(type: PaginatedVisits::class)),
            ),
            new OA\Response(
                response: 401,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)),
            ),
            new OA\Response(
                response: 404,
                description: 'No link with that id exists for this user.',
                content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)),
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded (60 req/min per token).',
                headers: [
                    new OA\Header(header: 'Retry-After', description: 'Seconds until the rate limit window resets.', schema: new OA\Schema(type: 'integer', minimum: 0)),
                ],
                content: new OA\JsonContent(ref: new Model(type: RateLimitErrorResponse::class)),
            ),
        ],
    )]
    public function listForLink(
        string $id,
        Request $request,
        LinkRepository $linkRepository,
        VisitRepository $visitRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $link = $linkRepository->findOneByIdAndUser($id, $user);
        if ($link === null) {
            return $this->responder->errorResponse('not_found', 'Link not found.', Response::HTTP_NOT_FOUND);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $visitRepository->findByLinkPaginated($link, $page, self::PER_PAGE);

        $data = [];
        foreach ($paginator as $visit) {
            $data[] = $this->serializer->toArray($visit);
        }

        return $this->responder->paginated($data, $page, self::PER_PAGE, count($paginator));
    }

    #[Route('/api/v1/visits/{id}', name: 'api_v1_visit_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a single visit by id',
        description: 'Returns the visit with the given id, if its parent link belongs to the authenticated user. Visits attached to other users\' links return 404 (no enumeration leak).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Visit UUID (v7).',
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '01933b8a-f000-7000-8a00-000000000001',
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The visit.',
                content: new OA\JsonContent(ref: new Model(type: VisitResource::class)),
            ),
            new OA\Response(
                response: 401,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)),
            ),
            new OA\Response(
                response: 404,
                description: 'No visit with that id exists for this user.',
                content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)),
            ),
            new OA\Response(
                response: 429,
                description: 'Rate limit exceeded (60 req/min per token).',
                headers: [
                    new OA\Header(header: 'Retry-After', description: 'Seconds until the rate limit window resets.', schema: new OA\Schema(type: 'integer', minimum: 0)),
                ],
                content: new OA\JsonContent(ref: new Model(type: RateLimitErrorResponse::class)),
            ),
        ],
    )]
    public function show(
        string $id,
        VisitRepository $visitRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $visit = $visitRepository->findOneByIdAndUser($id, $user);
        if ($visit === null) {
            return $this->responder->errorResponse('not_found', 'Visit not found.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->toArray($visit));
    }
}
