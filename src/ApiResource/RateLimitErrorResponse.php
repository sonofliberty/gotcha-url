<?php

namespace App\ApiResource;

use OpenApi\Attributes as OA;

/**
 * Schema-only DTO. Mirrors the 429 Too Many Requests body. Never instantiated
 * at runtime.
 */
#[OA\Schema(
    schema: 'RateLimitErrorResponse',
    description: 'Returned when the per-token rate limit (60 requests/minute, sliding window) is exceeded. The same value is also sent in the `Retry-After` response header.',
)]
class RateLimitErrorResponse
{
    #[OA\Property(type: 'string', example: 'rate_limited', description: 'Always `rate_limited` for this response.')]
    public string $error;

    #[OA\Property(type: 'string', example: 'Too many requests.', description: 'Human-readable summary.')]
    public string $message;

    #[OA\Property(
        type: 'integer',
        example: 42,
        minimum: 0,
        description: 'Seconds until the rate limit window resets and another request may succeed. Mirrors the `Retry-After` header.',
    )]
    public int $retry_after;
}
