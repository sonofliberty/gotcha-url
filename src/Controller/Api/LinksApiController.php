<?php

namespace App\Controller\Api;

use App\ApiResource\ErrorResponse;
use App\ApiResource\LinkResource;
use App\ApiResource\PaginatedLinks;
use App\ApiResource\RateLimitErrorResponse;
use App\ApiResource\ValidationErrorResponse;
use App\Entity\User;
use App\Repository\LinkRepository;
use App\Service\Api\ApiResponder;
use App\Service\LinkCreator;
use App\Service\LinkSerializer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

#[OA\Tag(
    name: 'Links',
    description: 'Create, read, and delete short links. All endpoints require Bearer authentication and share a per-token rate limit of 60 requests per minute (sliding window).',
)]
class LinksApiController extends AbstractController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly LinkSerializer $serializer,
        private readonly ApiResponder $responder,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/api/v1/links', name: 'api_v1_link_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new short link',
        description: <<<'TXT'
            Creates a `redirect` link (default) or a `page` link.

            - For `redirect` links, supply `target_url` (required).
            - For `page` links, set `type=page` and supply `content` (required); `target_url` is ignored.
            - Omit `slug` to auto-generate a 7-char alphanumeric one. Supply `slug` to use a custom 4-10 char alphanumeric value; collisions return 409.
            - On success, the response body is the created Link and the `Location` header points at its `GET` URL.
            TXT,
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Link creation payload.',
            content: new OA\JsonContent(
                required: ['target_url'],
                properties: [
                    new OA\Property(property: 'target_url', type: 'string', format: 'uri', maxLength: 2048, nullable: true, example: 'https://example.com/long/path', description: 'Destination URL. Required when `type=redirect` (default). Must use http or https. Ignored when `type=page`.'),
                    new OA\Property(property: 'type', type: 'string', enum: ['redirect', 'page'], default: 'redirect', example: 'redirect', description: 'Link kind. Defaults to `redirect`.'),
                    new OA\Property(property: 'slug', type: 'string', pattern: '^[a-zA-Z0-9]{4,10}$', minLength: 4, maxLength: 10, nullable: true, example: null, description: 'Custom 4-10 char alphanumeric slug. Auto-generated (7 chars) if omitted. Returns 409 if already in use.'),
                    new OA\Property(property: 'label', type: 'string', maxLength: 100, nullable: true, example: 'Spring campaign', description: 'Optional human-readable label shown only in the dashboard.'),
                    new OA\Property(property: 'tracking_enabled', type: 'boolean', default: true, example: true, description: 'When true (default), each visit records browser metadata and timestamp.'),
                    new OA\Property(property: 'content', type: 'string', maxLength: 50000, nullable: true, example: null, description: 'Required for `type=page`. Accepts Markdown, HTML, or plain text; scripts, event handlers, and unsafe URL schemes are stripped at render time.'),
                ],
                example: [
                    'target_url' => 'https://example.com/long/path',
                    'type' => 'redirect',
                    'slug' => 'spring',
                    'label' => 'Spring campaign',
                    'tracking_enabled' => true,
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Link created. The `Location` header points to the GET URL of the new link.',
                headers: [
                    new OA\Header(header: 'Location', description: 'Absolute URL of the created link resource.', schema: new OA\Schema(type: 'string', format: 'uri')),
                ],
                content: new OA\JsonContent(ref: new Model(type: LinkResource::class)),
            ),
            new OA\Response(
                response: 400,
                description: 'Request body is not valid JSON.',
                content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)),
            ),
            new OA\Response(
                response: 401,
                description: 'Missing or invalid bearer token.',
                content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)),
            ),
            new OA\Response(
                response: 409,
                description: 'Custom `slug` already in use by another link.',
                content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed. See `details` for per-field messages.',
                content: new OA\JsonContent(ref: new Model(type: ValidationErrorResponse::class)),
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
    public function create(
        Request $request,
        LinkCreator $linkCreator,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->responder->errorResponse('bad_request', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $customSlug = trim((string) ($data['slug'] ?? ''));
        $label = isset($data['label']) ? trim((string) $data['label']) : null;
        $targetUrl = isset($data['target_url']) ? trim((string) $data['target_url']) : null;
        $content = isset($data['content']) ? (string) $data['content'] : null;

        try {
            $result = $linkCreator->create(
                user: $user,
                type: (string) ($data['type'] ?? 'redirect'),
                targetUrl: $targetUrl,
                markdownContent: $content,
                label: $label,
                customSlug: $customSlug !== '' ? $customSlug : null,
                trackingEnabled: isset($data['tracking_enabled']) ? (bool) $data['tracking_enabled'] : true,
            );
        } catch (UniqueConstraintViolationException) {
            return $this->responder->errorResponse('slug_conflict', 'Slug already in use.', Response::HTTP_CONFLICT);
        }

        if ($result instanceof ConstraintViolationListInterface) {
            $details = [];
            foreach ($result as $error) {
                $path = $error->getPropertyPath() ?: '_';
                $details[$path] = (string) $error->getMessage();
            }
            return $this->responder->errorResponse('validation_failed', 'Request validation failed.', Response::HTTP_UNPROCESSABLE_ENTITY, $details);
        }

        $location = $this->urlGenerator->generate('api_v1_link_show', ['id' => $result->getId()->toRfc4122()]);

        return new JsonResponse(
            $this->serializer->toArray($result),
            Response::HTTP_CREATED,
            ['Location' => $location],
        );
    }

    #[Route('/api/v1/links', name: 'api_v1_link_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List your short links',
        description: 'Returns the authenticated user\'s links, newest first, paginated 20 per page. Only the requesting user\'s links are returned; other users\' links are never visible.',
        parameters: [
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
                description: 'Paginated list of links.',
                content: new OA\JsonContent(ref: new Model(type: PaginatedLinks::class)),
            ),
            new OA\Response(
                response: 401,
                description: 'Missing or invalid bearer token.',
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
    public function list(
        Request $request,
        LinkRepository $linkRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $linkRepository->findByUserPaginated($user, $page, self::PER_PAGE);

        $data = [];
        foreach ($paginator as $link) {
            $data[] = $this->serializer->toArray($link);
        }

        return $this->responder->paginated($data, $page, self::PER_PAGE, count($paginator));
    }

    #[Route('/api/v1/links/{id}', name: 'api_v1_link_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a single short link by id',
        description: 'Returns the link with the given id, if it belongs to the authenticated user. Links owned by other users return 404 (no enumeration leak).',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Link UUID (v7).',
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '01933b8a-e234-7000-8a00-000000000001',
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The link.',
                content: new OA\JsonContent(ref: new Model(type: LinkResource::class)),
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
    public function show(
        string $id,
        LinkRepository $linkRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $link = $linkRepository->findOneByIdAndUser($id, $user);
        if ($link === null) {
            return $this->responder->errorResponse('not_found', 'Link not found.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->toArray($link));
    }

    #[Route('/api/v1/links/{id}', name: 'api_v1_link_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a short link',
        description: 'Permanently deletes the link and cascades to all of its recorded visits. Returns 204 with no body on success. A second call with the same id returns 404.',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Link UUID (v7).',
                schema: new OA\Schema(type: 'string', format: 'uuid'),
                example: '01933b8a-e234-7000-8a00-000000000001',
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Link deleted. Response body is empty.'),
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
    public function delete(
        string $id,
        LinkRepository $linkRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $link = $linkRepository->findOneByIdAndUser($id, $user);
        if ($link === null) {
            return $this->responder->errorResponse('not_found', 'Link not found.', Response::HTTP_NOT_FOUND);
        }

        $em->remove($link);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
