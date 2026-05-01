<?php

namespace App\Controller\Api;

use App\ApiResource\LinkResource;
use App\Entity\Link;
use App\Entity\User;
use App\Repository\LinkRepository;
use App\Service\LinkSerializer;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Links')]
class LinksApiController extends AbstractController
{
    private const PER_PAGE = 20;
    private const ALLOWED_TYPES = ['redirect', 'page'];

    public function __construct(
        private readonly LinkSerializer $serializer,
        private readonly RateLimiterFactory $apiLimiter,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/api/v1/links', name: 'api_v1_link_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create a new short link',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['target_url'],
                properties: [
                    new OA\Property(property: 'target_url', type: 'string', format: 'uri', example: 'https://example.com/long/path', description: 'Required for type=redirect (default).'),
                    new OA\Property(property: 'type', type: 'string', enum: ['redirect', 'page'], example: 'redirect'),
                    new OA\Property(property: 'slug', type: 'string', pattern: '^[a-zA-Z0-9]{7}$', nullable: true, example: null, description: 'Custom 7-char slug. Auto-generated if omitted.'),
                    new OA\Property(property: 'label', type: 'string', maxLength: 100, nullable: true, example: 'Spring campaign'),
                    new OA\Property(property: 'tracking_enabled', type: 'boolean', default: true),
                    new OA\Property(property: 'content', type: 'string', nullable: true, description: 'Required for type=page. Accepts Markdown, HTML, or plain text; scripts, event handlers, and unsafe URL schemes are stripped at render time.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Link created', content: new OA\JsonContent(ref: new Model(type: LinkResource::class))),
            new OA\Response(response: 401, description: 'Missing or invalid bearer token'),
            new OA\Response(response: 409, description: 'Slug already in use'),
            new OA\Response(response: 422, description: 'Validation failed'),
            new OA\Response(response: 429, description: 'Rate limit exceeded'),
        ],
    )]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SlugGenerator $slugGenerator,
        ValidatorInterface $validator,
        LinkRepository $linkRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (($limited = $this->checkRateLimit($user)) !== null) {
            return $limited;
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->errorResponse('bad_request', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $type = in_array($data['type'] ?? null, self::ALLOWED_TYPES, true) ? $data['type'] : 'redirect';

        $link = new Link();
        $link->setUser($user);
        $link->setType($type);

        $customSlug = trim((string) ($data['slug'] ?? ''));
        if ($customSlug !== '') {
            if ($linkRepository->slugExists($customSlug)) {
                return $this->errorResponse('slug_conflict', 'Slug already in use.', Response::HTTP_CONFLICT);
            }
            $link->setSlug($customSlug);
        } else {
            $link->setSlug($slugGenerator->generate());
        }

        if ($type === 'page') {
            $link->setMarkdownContent(isset($data['content']) ? (string) $data['content'] : null);
        } else {
            $link->setTargetUrl(isset($data['target_url']) ? trim((string) $data['target_url']) : null);
        }

        if (isset($data['label'])) {
            $label = trim((string) $data['label']);
            $link->setLabel($label === '' ? null : $label);
        }

        if (isset($data['tracking_enabled'])) {
            $link->setTrackingEnabled((bool) $data['tracking_enabled']);
        }

        $errors = $validator->validate($link);
        if (count($errors) > 0) {
            $details = [];
            foreach ($errors as $error) {
                $path = $error->getPropertyPath() ?: '_';
                $details[$path] = (string) $error->getMessage();
            }
            return $this->errorResponse('validation_failed', 'Request validation failed.', Response::HTTP_UNPROCESSABLE_ENTITY, $details);
        }

        $em->persist($link);
        $em->flush();

        $location = $this->urlGenerator->generate('api_v1_link_show', ['id' => $link->getId()->toRfc4122()]);

        return new JsonResponse(
            $this->serializer->toArray($link),
            Response::HTTP_CREATED,
            ['Location' => $location],
        );
    }

    #[Route('/api/v1/links', name: 'api_v1_link_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List your short links',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: new Model(type: LinkResource::class))),
                        new OA\Property(property: 'pagination', properties: [
                            new OA\Property(property: 'page', type: 'integer'),
                            new OA\Property(property: 'per_page', type: 'integer'),
                            new OA\Property(property: 'total', type: 'integer'),
                            new OA\Property(property: 'total_pages', type: 'integer'),
                        ], type: 'object'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Missing or invalid bearer token'),
            new OA\Response(response: 429, description: 'Rate limit exceeded'),
        ],
    )]
    public function list(
        Request $request,
        LinkRepository $linkRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (($limited = $this->checkRateLimit($user)) !== null) {
            return $limited;
        }

        $page = max(1, $request->query->getInt('page', 1));
        $paginator = $linkRepository->findByUserPaginated($user, $page, self::PER_PAGE);
        $total = count($paginator);

        $data = [];
        foreach ($paginator as $link) {
            $data[] = $this->serializer->toArray($link);
        }

        return new JsonResponse([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            ],
        ]);
    }

    #[Route('/api/v1/links/{id}', name: 'api_v1_link_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get a single short link by id',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Link', content: new OA\JsonContent(ref: new Model(type: LinkResource::class))),
            new OA\Response(response: 401, description: 'Missing or invalid bearer token'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 429, description: 'Rate limit exceeded'),
        ],
    )]
    public function show(
        string $id,
        LinkRepository $linkRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (($limited = $this->checkRateLimit($user)) !== null) {
            return $limited;
        }

        $link = $linkRepository->findOneByIdAndUser($id, $user);
        if ($link === null) {
            return $this->errorResponse('not_found', 'Link not found.', Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serializer->toArray($link));
    }

    #[Route('/api/v1/links/{id}', name: 'api_v1_link_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete a short link (cascades to its visits)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 401, description: 'Missing or invalid bearer token'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 429, description: 'Rate limit exceeded'),
        ],
    )]
    public function delete(
        string $id,
        LinkRepository $linkRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (($limited = $this->checkRateLimit($user)) !== null) {
            return $limited;
        }

        $link = $linkRepository->findOneByIdAndUser($id, $user);
        if ($link === null) {
            return $this->errorResponse('not_found', 'Link not found.', Response::HTTP_NOT_FOUND);
        }

        $em->remove($link);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function checkRateLimit(User $user): ?JsonResponse
    {
        $limit = $this->apiLimiter->create($user->getAccountCode())->consume();
        if ($limit->isAccepted()) {
            return null;
        }

        $retryAfter = max(0, $limit->getRetryAfter()->getTimestamp() - time());

        return new JsonResponse(
            [
                'error' => 'rate_limited',
                'message' => 'Too many requests.',
                'retry_after' => $retryAfter,
            ],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => (string) $retryAfter],
        );
    }

    /**
     * @param array<string, string> $details
     */
    private function errorResponse(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $body = ['error' => $code, 'message' => $message];
        if ($details !== []) {
            $body['details'] = $details;
        }
        return new JsonResponse($body, $status);
    }
}
