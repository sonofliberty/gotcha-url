<?php

namespace App\ApiResource;

use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the JSON shape produced by VisitSerializer::toArray().
 * Used by Nelmio for OpenAPI documentation; never instantiated at runtime.
 */
#[OA\Schema(
    schema: 'Visit',
    description: 'A recorded visit to one of the authenticated user\'s links. Visits are immutable; they are created automatically when a visitor opens a tracked redirect or page.',
)]
class VisitResource
{
    #[OA\Property(
        type: 'string',
        format: 'uuid',
        example: '01933b8a-f000-7000-8a00-000000000001',
        description: 'UUID v7 identifier. Time-ordered: lexicographic sort matches creation order.',
    )]
    public string $id;

    #[OA\Property(
        type: 'string',
        format: 'uuid',
        example: '01933b8a-e234-7000-8a00-000000000001',
        description: 'UUID of the parent link.',
    )]
    public string $link_id;

    #[OA\Property(
        type: 'string',
        format: 'date-time',
        example: '2026-04-28T14:35:12+00:00',
        description: 'Visit timestamp in ISO 8601 / ATOM format (UTC).',
    )]
    public string $created_at;

    #[OA\Property(
        type: 'string',
        maxLength: 45,
        example: '203.0.113.42',
        description: 'Visitor IP address (IPv4 or IPv6). Captured from the request connection.',
    )]
    public string $ip_address;

    #[OA\Property(
        type: 'string',
        maxLength: 512,
        nullable: true,
        example: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        description: 'Raw User-Agent header sent by the browser.',
    )]
    public ?string $user_agent = null;

    #[OA\Property(
        type: 'string',
        maxLength: 2048,
        nullable: true,
        example: 'https://t.co/abc',
        description: 'Document referrer reported by the browser, if any.',
    )]
    public ?string $referrer = null;

    #[OA\Property(
        type: 'string',
        maxLength: 2,
        nullable: true,
        example: 'US',
        description: 'ISO 3166-1 alpha-2 country code from Cloudflare geo-IP.',
    )]
    public ?string $country_code = null;

    #[OA\Property(
        type: 'string',
        maxLength: 128,
        nullable: true,
        example: 'San Francisco',
        description: 'City name from Cloudflare geo-IP.',
    )]
    public ?string $city = null;

    #[OA\Property(
        type: 'string',
        maxLength: 20,
        nullable: true,
        example: '2560x1440',
        description: 'Reported screen resolution (`screen.width x screen.height`).',
    )]
    public ?string $screen_resolution = null;

    #[OA\Property(
        type: 'integer',
        nullable: true,
        example: 1440,
        description: 'Browser viewport width in CSS pixels.',
    )]
    public ?int $viewport_width = null;

    #[OA\Property(
        type: 'integer',
        nullable: true,
        example: 900,
        description: 'Browser viewport height in CSS pixels.',
    )]
    public ?int $viewport_height = null;

    #[OA\Property(
        type: 'string',
        maxLength: 10,
        nullable: true,
        example: '2',
        description: 'Device pixel ratio (`window.devicePixelRatio`). String to preserve fractional values.',
    )]
    public ?string $device_pixel_ratio = null;

    #[OA\Property(
        type: 'integer',
        nullable: true,
        example: 24,
        description: 'Color depth in bits (`screen.colorDepth`).',
    )]
    public ?int $color_depth = null;

    #[OA\Property(
        type: 'string',
        maxLength: 64,
        nullable: true,
        example: 'America/Los_Angeles',
        description: 'IANA timezone reported by the browser.',
    )]
    public ?string $timezone = null;

    #[OA\Property(
        type: 'string',
        maxLength: 10,
        nullable: true,
        example: 'en-US',
        description: 'BCP 47 language tag reported by the browser.',
    )]
    public ?string $language = null;

    #[OA\Property(
        type: 'string',
        maxLength: 64,
        nullable: true,
        example: 'MacIntel',
        description: 'Operating system platform string (`navigator.platform`).',
    )]
    public ?string $platform = null;

    #[OA\Property(
        type: 'string',
        maxLength: 64,
        nullable: true,
        example: 'Apple Computer, Inc.',
        description: 'Browser vendor string (`navigator.vendor`).',
    )]
    public ?string $vendor = null;

    #[OA\Property(
        type: 'boolean',
        nullable: true,
        example: true,
        description: 'Whether cookies are enabled in the browser.',
    )]
    public ?bool $cookies_enabled = null;

    #[OA\Property(
        type: 'boolean',
        nullable: true,
        example: false,
        description: 'Whether the visitor sent the Do-Not-Track signal.',
    )]
    public ?bool $do_not_track = null;

    #[OA\Property(
        type: 'boolean',
        nullable: true,
        example: true,
        description: 'Whether the browser has a built-in PDF viewer.',
    )]
    public ?bool $pdf_viewer_enabled = null;

    #[OA\Property(
        type: 'boolean',
        nullable: true,
        example: false,
        description: 'Whether the browser reports touch support.',
    )]
    public ?bool $touch_support = null;

    #[OA\Property(
        type: 'integer',
        nullable: true,
        example: 0,
        description: 'Maximum simultaneous touch points (`navigator.maxTouchPoints`).',
    )]
    public ?int $max_touch_points = null;

    #[OA\Property(
        type: 'integer',
        nullable: true,
        example: 8,
        description: 'Logical CPU cores reported by the browser (`navigator.hardwareConcurrency`).',
    )]
    public ?int $hardware_concurrency = null;

    #[OA\Property(
        type: 'string',
        maxLength: 10,
        nullable: true,
        example: '8',
        description: 'Approximate device memory in GB (`navigator.deviceMemory`). String to preserve original formatting.',
    )]
    public ?string $device_memory = null;

    #[OA\Property(
        type: 'string',
        maxLength: 20,
        nullable: true,
        example: '4g',
        description: 'Effective connection type (`navigator.connection.effectiveType`).',
    )]
    public ?string $connection_type = null;

    #[OA\Property(
        type: 'string',
        maxLength: 256,
        nullable: true,
        example: 'Apple GPU',
        description: 'Unmasked WebGL renderer string, when exposed by the browser.',
    )]
    public ?string $webgl_renderer = null;
}
