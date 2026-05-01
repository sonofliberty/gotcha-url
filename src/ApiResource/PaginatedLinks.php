<?php

namespace App\ApiResource;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the JSON shape returned by GET /api/v1/links.
 * Never instantiated at runtime.
 */
#[OA\Schema(
    schema: 'PaginatedLinks',
    description: 'Paginated list of links owned by the authenticated user, newest first.',
    example: [
        'data' => [
            [
                'id' => '01933b8a-e234-7000-8a00-000000000001',
                'slug' => 'abc1234',
                'short_url' => 'https://example.com/abc1234',
                'type' => 'redirect',
                'target_url' => 'https://example.com/long/path',
                'label' => 'Spring campaign',
                'tracking_enabled' => true,
                'content' => null,
                'visit_count' => 0,
                'created_at' => '2026-04-28T14:30:00+00:00',
            ],
        ],
        'pagination' => [
            'page' => 1,
            'per_page' => 20,
            'total' => 1,
            'total_pages' => 1,
        ],
    ],
)]
class PaginatedLinks
{
    /** @var LinkResource[] */
    #[OA\Property(
        type: 'array',
        items: new OA\Items(ref: new Model(type: LinkResource::class)),
        description: 'Links on this page, newest first.',
    )]
    public array $data;

    #[OA\Property(ref: new Model(type: Pagination::class))]
    public Pagination $pagination;
}
