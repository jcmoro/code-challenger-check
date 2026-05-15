<?php

declare(strict_types=1);

namespace App\Application\Provider;

use App\Domain\Car\CarType;
use App\Domain\Car\CarUse;
use App\Domain\Driver\DriverAge;
use App\Domain\Quote\Quote;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
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
 *
 * The algorithm is split into three phases that share state via an internal
 * {@see FetchSession} scratchpad:
 *   1. {@see startAllRequests}        — fires every provider's request.
 *   2. {@see processStreamedResponses} — drains chunks under the timeout.
 *   3. {@see buildFetchResult}         — aggregates the final list of quotes.
 */
#[WithMonologChannel('calculate')]
final readonly class ParallelQuoteFetcher implements QuoteFetcher
{
    /**
     * @param iterable<QuoteProvider> $providers
     */
    public function __construct(
        private iterable $providers,
        private HttpClientInterface $httpClient,
        private int $timeoutSeconds,
        private LoggerInterface $logger,
    ) {}

    public function fetchAll(DriverAge $age, CarType $type, CarUse $use): FetchResult
    {
        $session = $this->startAllRequests($age, $type, $use);
        $this->processStreamedResponses($session);

        return $this->buildFetchResult($session);
    }

    /**
     * Phase 1 — kick off every provider's HTTP call (non-blocking) and
     * record startup-time failures in the session.
     */
    private function startAllRequests(DriverAge $age, CarType $type, CarUse $use): FetchSession
    {
        $session = new FetchSession();

        foreach ($this->providers as $provider) {
            $providerId = $provider->id();
            $session->startedAt[$providerId] = microtime(true);
            try {
                $response = $provider->startRequest($age, $type, $use);
                $session->providersByResponse->attach($response, $provider);
                $session->responses[] = $response;
            } catch (\Throwable $e) {
                $session->failedProviderIds[] = $providerId;
                $session->outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::FAILED, $session->startedAt[$providerId]);
                $this->logger->warning('Provider failed to start request', [
                    'provider' => $providerId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $session;
    }

    /**
     * Phase 2 — iterate the multiplexed response stream under the global
     * timeout, dispatching each chunk to {@see handleChunk}.
     */
    private function processStreamedResponses(FetchSession $session): void
    {
        try {
            foreach ($this->httpClient->stream($session->responses, (float) $this->timeoutSeconds) as $response => $chunk) {
                $provider = $session->providersByResponse[$response] ?? null;
                if (null === $provider) {
                    continue;
                }
                $this->handleChunk($session, $provider, $response, $chunk);
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Stream-level transport failure', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * One-chunk dispatcher. Three branches matter:
     *   - timeout              → mark TIMEOUT and cancel.
     *   - first chunk + non-2xx → mark FAILED, cancel; eagerly read status
     *                              so the stream doesn't post-yield throw.
     *   - last chunk           → finalize the response into a Quote (or FAILED).
     */
    private function handleChunk(
        FetchSession $session,
        QuoteProvider $provider,
        ResponseInterface $response,
        ChunkInterface $chunk,
    ): void {
        $providerId = $provider->id();

        try {
            if ($chunk->isTimeout()) {
                $this->markResolvedFailure($session, $providerId, ProviderOutcome::TIMEOUT);
                $this->logger->warning('Provider timed out', ['provider' => $providerId]);
                $response->cancel();

                return;
            }

            if ($chunk->isFirst() && !$this->isSuccessfulStatus($response, $session, $providerId)) {
                $response->cancel();

                return;
            }

            if ($chunk->isLast() && !isset($session->resolved[$providerId])) {
                $entry = $this->finalize($provider, $response);
                $session->resolved[$providerId] = $entry;
                $session->outcomes[$providerId] = $this->outcome(
                    $providerId,
                    true === $entry ? ProviderOutcome::FAILED : ProviderOutcome::OK,
                    $session->startedAt[$providerId],
                );
            }
        } catch (TransportExceptionInterface $e) {
            $this->markResolvedFailure($session, $providerId, ProviderOutcome::FAILED);
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

    /**
     * Eagerly reads the status code on the first chunk. This forces the
     * stream's post-yield `getHeaders(true)` short-circuit so non-2xx
     * responses don't escape as exceptions. Returns false when the status
     * is non-2xx (caller cancels).
     */
    private function isSuccessfulStatus(
        ResponseInterface $response,
        FetchSession $session,
        string $providerId,
    ): bool {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 200 && $statusCode < 300) {
            return true;
        }
        $this->markResolvedFailure($session, $providerId, ProviderOutcome::FAILED);
        $this->logger->warning('Provider returned non-2xx', [
            'provider' => $providerId,
            'status' => $statusCode,
        ]);

        return false;
    }

    /**
     * Phase 3 — aggregate quotes + failed ids + outcomes from the session.
     * Any provider that started but never resolved is treated as TIMEOUT we
     * never observed (defensive — should not happen with HttpClient::stream()).
     */
    private function buildFetchResult(FetchSession $session): FetchResult
    {
        $quotes = [];
        foreach ($session->providersByResponse as $response) {
            $provider = $session->providersByResponse[$response];
            $providerId = $provider->id();
            $entry = $session->resolved[$providerId] ?? true;
            if (true === $entry) {
                $session->failedProviderIds[] = $providerId;
                if (!isset($session->outcomes[$providerId])) {
                    $session->outcomes[$providerId] = $this->outcome($providerId, ProviderOutcome::TIMEOUT, $session->startedAt[$providerId]);
                }
                continue;
            }
            $quotes[] = $entry;
        }

        return new FetchResult(
            quotes: $quotes,
            failedProviderIds: array_values(array_unique($session->failedProviderIds)),
            outcomes: $session->outcomes,
        );
    }

    private function markResolvedFailure(FetchSession $session, string $providerId, string $kind): void
    {
        $session->resolved[$providerId] = true;
        $session->outcomes[$providerId] = $this->outcome($providerId, $kind, $session->startedAt[$providerId]);
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

/**
 * Mutable scratchpad shared between the three phases of one fan-out
 * execution. Internal to {@see ParallelQuoteFetcher}; exposed here only
 * because PHP doesn't support nested classes.
 *
 * @internal
 */
final class FetchSession
{
    /** @var \SplObjectStorage<ResponseInterface, QuoteProvider> */
    public \SplObjectStorage $providersByResponse;

    /** @var list<ResponseInterface> */
    public array $responses = [];

    /** @var array<string, float> providerId => microtime when startRequest was invoked */
    public array $startedAt = [];

    /** @var array<string, ProviderOutcome> */
    public array $outcomes = [];

    /** @var list<string> */
    public array $failedProviderIds = [];

    /**
     * Keyed by provider id; `true` means the response was rejected
     * (timeout / non-2xx / parse error). Otherwise contains the Quote.
     *
     * @var array<string, Quote|true>
     */
    public array $resolved = [];

    public function __construct()
    {
        $this->providersByResponse = new \SplObjectStorage();
    }
}
