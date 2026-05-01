<?php

namespace App\ApiResource;

use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the 422 Unprocessable Entity body returned when
 * request validation fails. Never instantiated at runtime.
 */
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    description: 'Returned when request validation fails. The `details` map carries per-field messages keyed by property path.',
)]
class ValidationErrorResponse
{
    #[OA\Property(type: 'string', example: 'validation_failed', description: 'Always `validation_failed` for this response.')]
    public string $error;

    #[OA\Property(type: 'string', example: 'Request validation failed.', description: 'Human-readable summary.')]
    public string $message;

    #[OA\Property(
        type: 'object',
        description: 'Per-field validation messages. Keys are property paths from the entity (e.g. `targetUrl`, `slug`, `markdownContent`); values are human-readable error messages. May be empty if the failure is not field-scoped.',
        example: [
            'targetUrl' => 'This value is not a valid URL.',
            'slug' => 'Slug must be 4-10 alphanumeric characters.',
        ],
        additionalProperties: new OA\AdditionalProperties(type: 'string'),
    )]
    public array $details;
}
