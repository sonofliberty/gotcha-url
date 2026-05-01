<?php

namespace App\ApiResource;

use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the JSON envelope produced by error responses
 * (400, 401, 404, 409). Never instantiated at runtime.
 */
#[OA\Schema(
    schema: 'ErrorResponse',
    description: 'Standard error envelope. The `error` code is stable; `message` is human-readable and may change.',
)]
class ErrorResponse
{
    #[OA\Property(
        type: 'string',
        example: 'not_found',
        enum: ['bad_request', 'unauthorized', 'not_found', 'slug_conflict', 'validation_failed', 'rate_limited'],
        description: 'Stable machine-readable error code. Match on this, not on `message`.',
    )]
    public string $error;

    #[OA\Property(
        type: 'string',
        example: 'Link not found.',
        description: 'Human-readable message describing the error. Not stable across versions; intended for logs and UI display.',
    )]
    public string $message;
}
