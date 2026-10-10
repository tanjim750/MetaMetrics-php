<?php

declare(strict_types=1);

namespace MetaMetrics\Hierarchy;

use JsonSerializable;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Insights\DateRange\DateRange;

final readonly class HierarchyResult implements JsonSerializable
{
    private ?string $currency;

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
        ?string $currency = null,
    ) {
        $this->assertLevel($campaigns, HistoricalLevel::CAMPAIGN);
        $this->assertLevel($unattachedAdSets, HistoricalLevel::AD_SET);
        $this->assertLevel($unattachedAds, HistoricalLevel::AD);

        $this->campaigns = array_values($campaigns);
        $this->unattachedAdSets = array_values($unattachedAdSets);
        $this->unattachedAds = array_values($unattachedAds);
        $nodes = [...$campaigns, ...$unattachedAdSets, ...$unattachedAds];
        $this->currency = $currency ?? ($nodes === [] ? null : $nodes[0]->historicalResult()->currency());

        foreach ($nodes as $node) {
            $this->assertCurrency($node);
        }
    }

    public function currency(): ?string { return $this->currency; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'requested_period' => $this->requestedPeriod->toArray(),
            'campaigns' => array_map($this->nodeArray(...), $this->campaigns),
            'unattached_adsets' => array_map($this->nodeArray(...), $this->unattachedAdSets),
            'unattached_ads' => array_map($this->nodeArray(...), $this->unattachedAds),
        ];
    }

    public function jsonSerialize(): array { return $this->toArray(); }

    /** @return array<string, mixed> */
    private function nodeArray(HierarchyNode $node): array
    {
        return [
            ...$node->historicalResult()->toArray(false),
            'children' => array_map($this->nodeArray(...), $node->children()),
        ];
    }

    private function assertCurrency(HierarchyNode $node): void
    {
        if ($node->historicalResult()->currency() !== $this->currency) {
            throw new InvalidInputException('Hierarchy nodes must share the response currency.');
        }

        foreach ($node->children() as $child) {
            $this->assertCurrency($child);
        }
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
