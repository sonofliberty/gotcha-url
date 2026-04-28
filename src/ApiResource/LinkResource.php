<?php

namespace App\ApiResource;

use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the JSON shape produced by LinkSerializer::toArray().
 * Used by Nelmio for OpenAPI documentation; never instantiated at runtime.
 */
#[OA\Schema(schema: 'Link')]
class LinkResource
{
    #[OA\Property(type: 'string', format: 'uuid', example: '01933b8a-e234-7000-8a00-000000000001')]
    public string $id;

    #[OA\Property(type: 'string', example: 'abc1234')]
    public string $slug;

    #[OA\Property(type: 'string', format: 'uri', example: 'http://localhost:8080/abc1234')]
    public string $short_url;

    #[OA\Property(type: 'string', enum: ['redirect', 'page'], example: 'redirect')]
    public string $type;

    #[OA\Property(type: 'string', format: 'uri', nullable: true, example: 'https://example.com/long/path')]
    public ?string $target_url = null;

    #[OA\Property(type: 'string', nullable: true, example: 'Spring campaign')]
    public ?string $label = null;

    #[OA\Property(type: 'boolean', example: true)]
    public bool $tracking_enabled;

    #[OA\Property(type: 'string', nullable: true, example: null)]
    public ?string $markdown_content = null;

    #[OA\Property(type: 'integer', example: 0)]
    public int $visit_count;

    #[OA\Property(type: 'string', format: 'date-time', example: '2026-04-28T14:30:00+00:00')]
    public string $created_at;
}
