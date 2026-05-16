<?php

declare(strict_types=1);

namespace App\UI\Http\EventListener;

use App\UI\Http\Response\ValidationErrorResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Normalises Symfony's MapRequestPayload validation failures (422) into the
 * spec's `{ error: "validation_failed", violations: [...] }` envelope at 400.
 */
#[AsEventListener(event: ExceptionEvent::class)]
final readonly class ValidationFailedListener
{
    public function __construct(
        private ValidationErrorResponse $validationErrorFactory,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        $previous = $throwable->getPrevious();

        if (!$throwable instanceof HttpException || !$previous instanceof ValidationFailedException) {
            return;
        }

        $event->setResponse($this->validationErrorFactory->fromViolations($previous->getViolations()));
    }
}
