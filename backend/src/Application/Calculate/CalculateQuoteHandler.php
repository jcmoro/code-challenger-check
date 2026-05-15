<?php

declare(strict_types=1);

namespace App\Application\Calculate;

use App\Application\Campaign\CampaignProvider;
use App\Application\Provider\ProviderOutcome;
use App\Application\Provider\QuoteFetcher;
use App\Domain\Driver\DriverAge;
use App\Domain\Quote\Quote;
use App\Infrastructure\System\Clock;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('calculate')]
final readonly class CalculateQuoteHandler
{
    public function __construct(
        private QuoteFetcher $fetcher,
        private CampaignProvider $campaignProvider,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    public function handle(CalculateQuoteCommand $command): CalculateQuoteResult
    {
        $started = microtime(true);
        $requestId = bin2hex(random_bytes(8));

        $age = DriverAge::fromBirthday($command->driverBirthday, $this->clock->now());
        $age->assertInsurable();
        $campaign = $this->campaignProvider->state();

        $fetch = $this->fetcher->fetchAll($age, $command->carType, $command->carUse);

        $quotes = $fetch->quotes;
        if ($campaign->active) {
            $multiplier = $campaign->customerPaysMultiplier();
            $quotes = array_map(
                static fn(Quote $q): Quote => $q->withDiscountedPrice($q->price->multiply($multiplier)->rounded()),
                $quotes,
            );
        }

        usort($quotes, static function (Quote $a, Quote $b): int {
            $cmp = $a->finalPrice()->amount <=> $b->finalPrice()->amount;

            return 0 !== $cmp ? $cmp : strcmp($a->providerId, $b->providerId);
        });

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->logger->info('calculate_completed', [
            'request_id' => $requestId,
            'duration_ms' => $durationMs,
            'campaign_active' => $campaign->active,
            'campaign_percentage' => $campaign->percentage,
            'quotes_count' => \count($quotes),
            'failed_providers' => $fetch->failedProviderIds,
            'providers' => $this->serializeOutcomes($fetch->outcomes),
        ]);

        return new CalculateQuoteResult(
            campaign: $campaign,
            quotes: $quotes,
            failedProviderIds: $fetch->failedProviderIds,
            durationMs: $durationMs,
        );
    }

    /**
     * @param array<string, ProviderOutcome> $outcomes
     *
     * @return array<string, array{outcome: string, duration_ms: int}>
     */
    private function serializeOutcomes(array $outcomes): array
    {
        $serialized = [];
        foreach ($outcomes as $id => $outcome) {
            $serialized[$id] = [
                'outcome' => $outcome->outcome,
                'duration_ms' => $outcome->durationMs,
            ];
        }

        return $serialized;
    }
}
