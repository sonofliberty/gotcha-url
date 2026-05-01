<?php

namespace App\ApiResource;

use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the JSON shape produced by LinkSerializer::toArray().
 * Used by Nelmio for OpenAPI documentation; never instantiated at runtime.
 */
#[OA\Schema(
    schema: 'Link',
    description: 'A short link owned by the authenticated user. Either a `redirect` (forwards visitors to `target_url`) or a `page` (renders `content` as a standalone page).',
)]
class LinkResource
{
    #[OA\Property(
        type: 'string',
        format: 'uuid',
        example: '01933b8a-e234-7000-8a00-000000000001',
        description: 'UUID v7 identifier. Time-ordered: lexicographic sort matches creation order.',
    )]
    public string $id;

    #[OA\Property(
        type: 'string',
        pattern: '^[a-zA-Z0-9]{4,10}$',
        minLength: 4,
        maxLength: 10,
        example: 'abc1234',
        description: 'URL-safe alphanumeric path segment. 4-10 chars. Custom-supplied on creation or auto-generated (7 chars) when omitted.',
    )]
    public string $slug;

    #[OA\Property(
        type: 'string',
        format: 'uri',
        example: 'https://example.com/abc1234',
        description: 'Fully-qualified short URL. Concatenation of the configured app base URL and `slug`. Share this with end users.',
    )]
    public string $short_url;

    #[OA\Property(
        type: 'string',
        enum: ['redirect', 'page'],
        example: 'redirect',
        description: 'Link kind. `redirect` forwards visitors to `target_url`; `page` renders `content` as a standalone page on the short URL.',
    )]
    public string $type;

    #[OA\Property(
        type: 'string',
        format: 'uri',
        maxLength: 2048,
        nullable: true,
        example: 'https://example.com/long/path',
        description: 'Destination URL for `redirect` links. Must use http or https. Always null for `page` links.',
    )]
    public ?string $target_url = null;

    #[OA\Property(
        type: 'string',
        maxLength: 100,
        nullable: true,
        example: 'Spring campaign',
        description: 'Optional human-readable label shown in the dashboard. Not visible to visitors.',
    )]
    public ?string $label = null;

    #[OA\Property(
        type: 'boolean',
        example: true,
        description: 'When true, visits to this link record a `Visit` row (browser metadata, timestamp). When false, redirects/pages serve immediately and no visit data is collected.',
    )]
    public bool $tracking_enabled;

    #[OA\Property(
        type: 'string',
        maxLength: 50000,
        nullable: true,
        example: null,
        description: 'Page content for `page` links. Accepts Markdown, HTML, or plain text; auto-detected by content shape. Sanitized at render time: scripts, event handlers, and unsafe URL schemes are stripped. Always null for `redirect` links.',
    )]
    public ?string $content = null;

    #[OA\Property(
        type: 'integer',
        minimum: 0,
        example: 0,
        description: 'Number of recorded visits to this link. Always 0 when `tracking_enabled` is false.',
    )]
    public int $visit_count;

    #[OA\Property(
        type: 'string',
        format: 'date-time',
        example: '2026-04-28T14:30:00+00:00',
        description: 'Creation timestamp in ISO 8601 / ATOM format (UTC).',
    )]
    public string $created_at;
}
