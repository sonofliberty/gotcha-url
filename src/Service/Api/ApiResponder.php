<?php

namespace App\Service\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ApiResponder
{
    /**
     * @param array<string, string> $details
     */
    public function errorResponse(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        $body = ['error' => $code, 'message' => $message];
        if ($details !== []) {
            $body['details'] = $details;
        }
        return new JsonResponse($body, $status);
    }

    public function validationErrorResponse(ConstraintViolationListInterface $errors): JsonResponse
    {
        $details = [];
        foreach ($errors as $error) {
            $path = $error->getPropertyPath() ?: '_';
            $details[$path] = (string) $error->getMessage();
        }
        return $this->errorResponse('validation_failed', 'Request validation failed.', Response::HTTP_UNPROCESSABLE_ENTITY, $details);
    }

    /**
     * @param array<int, array<string, mixed>> $data Already-serialized items for the current page.
     */
    public function paginated(array $data, int $page, int $perPage, int $total): JsonResponse
    {
        return new JsonResponse([
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }
}
