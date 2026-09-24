<?php

declare(strict_types=1);

namespace MetaMetrics\Hierarchy;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Insights\DateRange\DateRange;

final readonly class HierarchyResult
{
    /** @var list<HierarchyNode> */
    private array $campaigns;

    /** @var list<HierarchyNode> */
    private array $unattachedAdSets;

    /** @var list<HierarchyNode> */
    private array $unattachedAds;

    /**
     * @param list<HierarchyNode> $campaigns
     * @param list<HierarchyNode> $unattachedAdSets
     * @param list<HierarchyNode> $unattachedAds
     */
    public function __construct(
        private DateRange $requestedPeriod,
        array $campaigns,
        array $unattachedAdSets = [],
        array $unattachedAds = [],
    ) {
        $this->assertLevel($campaigns, HistoricalLevel::CAMPAIGN);
        $this->assertLevel($unattachedAdSets, HistoricalLevel::AD_SET);
        $this->assertLevel($unattachedAds, HistoricalLevel::AD);

        $this->campaigns = array_values($campaigns);
        $this->unattachedAdSets = array_values($unattachedAdSets);
        $this->unattachedAds = array_values($unattachedAds);
    }

    public function requestedPeriod(): DateRange
    {
        return $this->requestedPeriod;
    }

    /** @return list<HierarchyNode> */
    public function campaigns(): array
    {
        return $this->campaigns;
    }

    /** @return list<HierarchyNode> */
    public function unattachedAdSets(): array
    {
        return $this->unattachedAdSets;
    }

    /** @return list<HierarchyNode> */
    public function unattachedAds(): array
    {
        return $this->unattachedAds;
    }

    /** @param list<HierarchyNode> $nodes */
    private function assertLevel(array $nodes, HistoricalLevel $level): void
    {
        foreach ($nodes as $node) {
            if (!$node instanceof HierarchyNode || $node->level() !== $level) {
                throw new InvalidInputException(sprintf(
                    'Hierarchy collection requires %s nodes.',
                    $level->value,
                ));
            }

            if ($node->historicalResult()->requestedPeriod()->toArray()
                !== $this->requestedPeriod->toArray()) {
                throw new InvalidInputException('Hierarchy nodes must use the hierarchy requested period.');
            }
        }
    }
}
