<?php

namespace App\ApiResource;

use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the `pagination` object embedded in paginated list
 * responses. Never instantiated at runtime.
 */
#[OA\Schema(
    schema: 'Pagination',
    description: 'Pagination metadata. Page size varies by endpoint.',
)]
class Pagination
{
    #[OA\Property(type: 'integer', minimum: 1, example: 1, description: 'Current page (1-indexed).')]
    public int $page;

    #[OA\Property(type: 'integer', example: 20, description: 'Items per page. Varies by endpoint.')]
    public int $per_page;

    #[OA\Property(type: 'integer', minimum: 0, example: 1, description: 'Total number of items across all pages.')]
    public int $total;

    #[OA\Property(type: 'integer', minimum: 1, example: 1, description: 'Total page count. Always at least 1, even when `total` is 0.')]
    public int $total_pages;
}
