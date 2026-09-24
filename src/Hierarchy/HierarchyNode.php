<?php

declare(strict_types=1);

namespace MetaMetrics\Hierarchy;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Historical\HistoricalResult;

final readonly class HierarchyNode
{
    /** @var list<self> */
    private array $children;

    /** @param list<self> $children */
    public function __construct(
        private HistoricalResult $historicalResult,
        array $children = [],
    ) {
        foreach ($children as $child) {
            if (!$child instanceof self) {
                throw new InvalidInputException('Hierarchy children must be HierarchyNode instances.');
            }

            $this->validateChild($child);
        }

        $this->children = array_values($children);
    }

    public function historicalResult(): HistoricalResult
    {
        return $this->historicalResult;
    }

    public function entityId(): string
    {
        return $this->historicalResult->entityId();
    }

    public function level(): HistoricalLevel
    {
        return $this->historicalResult->level();
    }

    /** @return list<self> */
    public function children(): array
    {
        return $this->children;
    }

    private function validateChild(self $child): void
    {
        $validRelationship = match ($this->level()) {
            HistoricalLevel::CAMPAIGN => $child->level() === HistoricalLevel::AD_SET
                && $child->historicalResult()->campaignId() === $this->entityId(),
            HistoricalLevel::AD_SET => $child->level() === HistoricalLevel::AD
                && $child->historicalResult()->adSetId() === $this->entityId(),
            HistoricalLevel::AD => false,
        };

        $sameReportingPeriod = $child->historicalResult()->insights()->dateStart()
                === $this->historicalResult->insights()->dateStart()
            && $child->historicalResult()->insights()->dateStop()
                === $this->historicalResult->insights()->dateStop();

        if (!$validRelationship || !$sameReportingPeriod) {
            throw new InvalidInputException('Hierarchy child does not match its parent level, ID, and reporting period.');
        }
    }
}
