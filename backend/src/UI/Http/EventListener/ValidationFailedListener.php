<?php

declare(strict_types=1);

namespace App\UI\Http\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Normalises Symfony's MapRequestPayload validation failures (422) into the
 * spec's `{ error: "validation_failed", violations: [...] }` envelope at 400.
 */
#[AsEventListener(event: ExceptionEvent::class)]
final class ValidationFailedListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $previous = $throwable->getPrevious();

        if (!$throwable instanceof HttpException || !$previous instanceof ValidationFailedException) {
            return;
        }

        $violations = [];
        foreach ($previous->getViolations() as $violation) {
            $violations[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        $event->setResponse(new JsonResponse(
            ['error' => 'validation_failed', 'violations' => $violations],
            JsonResponse::HTTP_BAD_REQUEST,
        ));
    }
}
