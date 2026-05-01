<?php

namespace App\ApiResource;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the JSON shape returned by GET /api/v1/links/{id}/visits.
 * Never instantiated at runtime.
 */
#[OA\Schema(
    schema: 'PaginatedVisits',
    description: 'Paginated list of visits to a link owned by the authenticated user, newest first. Page size is 50.',
)]
class PaginatedVisits
{
    /** @var VisitResource[] */
    #[OA\Property(
        type: 'array',
        items: new OA\Items(ref: new Model(type: VisitResource::class)),
        description: 'Visits on this page, newest first.',
    )]
    public array $data;

    #[OA\Property(ref: new Model(type: Pagination::class))]
    public Pagination $pagination;
}
