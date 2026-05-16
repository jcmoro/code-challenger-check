<?php

declare(strict_types=1);

namespace App\UI\Http\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Single source of truth for the `{ error: 'validation_failed', violations: [] }`
 * envelope returned at HTTP 400. Both the `MapRequestPayload` exception
 * listener (Symfony validator failures) and the controller (domain
 * exceptions) build their 400 response through this factory so the wire
 * format stays in one place.
 */
final readonly class ValidationErrorResponse
{
    /**
     * Build a 400 from a single (field, message) pair — used when the
     * domain throws and the controller maps it onto a specific field.
     */
    public function fromField(string $field, string $message): JsonResponse
    {
        return self::build([
            ['field' => $field, 'message' => $message],
        ]);
    }

    /**
     * Build a 400 from Symfony's validator output — used by the
     * MapRequestPayload exception listener for DTO-level violations.
     */
    public function fromViolations(ConstraintViolationListInterface $violations): JsonResponse
    {
        $items = [];
        foreach ($violations as $violation) {
            $items[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return self::build($items);
    }

    /**
     * @param list<array{field: string, message: string}> $violations
     */
    private static function build(array $violations): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'validation_failed', 'violations' => $violations],
            JsonResponse::HTTP_BAD_REQUEST,
        );
    }
}
