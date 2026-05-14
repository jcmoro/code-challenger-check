<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Quote\Quote;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fans out to every registered QuoteProvider in parallel and collects the
 * surviving Quotes. Any provider that errors, returns non-2xx, returns an
 * un-parseable body, or fails to emit a chunk within the timeout is *dropped*
 * (recorded in `failedProviderIds`) — never propagated as an error.
 *
 * Per-provider outcomes (ok/failed/timeout + duration) are exposed on the
 * result so the handler can emit a single structured log line summarising
 * the whole fan-out.
 */
final readonly class ParallelQuoteFetcher implements QuoteFetcher
{
    /**
     * @param iterable<QuoteProvider> $providers
     */
    public function __construct(
        #[AutowireIterator('app.quote_provider')]
        private iterable $providers,
        private HttpClientInterface $httpClient,
        #[Autowire('%env(int:PROVIDER_TIMEOUT_SECONDS)%')]
        private int $timeoutSeconds = 10,
        #[Autowire(service: 'monolog.logger.calculate')]
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function fetchAll(DriverAge $age, CarType $type, CarUse $use): FetchResult
    {
        $providersByResponse = new \SplObjectStorage();
        $responses = [];
        $startedAt = [];            // providerId => float microtime
        $outcomes = [];             // providerId => ProviderOutcome
        $failedProviderIds = [];

        foreach ($this->providers as $provider) {
            $providerId = $provider->id();
            $startedAt[$providerId] = microtime(true);
            try {
                $response = $provider->startRequest($age, $type, $use);
                $providersByResponse->attach($response, $provider);
                $responses[] = $response;
            } catch (\Throwable $e) {
                $failedProviderIds[] = $providerId;
                $outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::FAILED, $startedAt[$providerId]);
                $this->logger->warning('Provider failed to start request', [
                    'provider' => $providerId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        /** @var array<string, Quote|true> $resolved keyed by provider id; `true` means failed */
        $resolved = [];

        try {
            foreach ($this->httpClient->stream($responses, (float) $this->timeoutSeconds) as $response => $chunk) {
                $provider = $providersByResponse[$response] ?? null;
                if (null === $provider) {
                    continue;
                }
                $providerId = $provider->id();

                try {
                    if ($chunk->isTimeout()) {
                        $resolved[$providerId] = true;
                        $outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::TIMEOUT, $startedAt[$providerId]);
                        $this->logger->warning('Provider timed out', ['provider' => $providerId]);
                        $response->cancel();
                        continue;
                    }

                    // On the first chunk, eagerly resolve status so the stream
                    // doesn't post-yield `getHeaders(true)` and throw on non-2xx.
                    if ($chunk->isFirst()) {
                        $statusCode = $response->getStatusCode();
                        if ($statusCode < 200 || $statusCode >= 300) {
                            $resolved[$providerId] = true;
                            $outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::FAILED, $startedAt[$providerId]);
                            $this->logger->warning('Provider returned non-2xx', [
                                'provider' => $providerId,
                                'status' => $statusCode,
                            ]);
                            $response->cancel();
                            continue;
                        }
                    }

                    if ($chunk->isLast() && !isset($resolved[$providerId])) {
                        $entry = $this->finalize($provider, $response);
                        $resolved[$providerId] = $entry;
                        $outcomes[$providerId] = $this->outcome(
                            $providerId,
                            true === $entry ? ProviderOutcome::FAILED : ProviderOutcome::OK,
                            $startedAt[$providerId],
                        );
                    }
                } catch (TransportExceptionInterface $e) {
                    $resolved[$providerId] = true;
                    $outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::FAILED, $startedAt[$providerId]);
                    $this->logger->warning('Provider transport failure', [
                        'provider' => $providerId,
                        'exception' => $e->getMessage(),
                    ]);
                    try {
                        $response->cancel();
                    } catch (\Throwable) {
                        // already cancelled / completed — nothing to do
                    }
                }
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Stream-level transport failure', ['exception' => $e->getMessage()]);
        }

        // Any started response that never resolved is a timeout we never observed.
        $quotes = [];
        foreach ($providersByResponse as $response) {
            $provider = $providersByResponse[$response];
            $providerId = $provider->id();
            $entry = $resolved[$providerId] ?? true;
            if (true === $entry) {
                $failedProviderIds[] = $providerId;
                if (!isset($outcomes[$providerId])) {
                    $outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::TIMEOUT, $startedAt[$providerId]);
                }
                continue;
            }
            $quotes[] = $entry;
        }

        return new FetchResult(
            quotes: $quotes,
            failedProviderIds: array_values(array_unique($failedProviderIds)),
            outcomes: $outcomes,
        );
    }

    /**
     * @return Quote|true `true` means the response was unusable
     */
    private function finalize(QuoteProvider $provider, ResponseInterface $response): Quote|bool
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->warning('Provider returned non-2xx', [
                'provider' => $provider->id(),
                'status' => $statusCode,
            ]);
            $response->cancel();

            return true;
        }

        $quote = $provider->parseResponse($response);
        if (null === $quote) {
            $this->logger->warning('Provider returned unparseable body', [
                'provider' => $provider->id(),
            ]);
            $response->cancel();

            return true;
        }

        return $quote;
    }

    private function outcome(string $providerId, string $outcome, float $startedAt): ProviderOutcome
    {
        return new ProviderOutcome(
            providerId: $providerId,
            outcome: $outcome,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
        );
    }
}
