<?php

declare(strict_types=1);

namespace MetaMetrics\Hierarchy;

use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Historical\HistoricalQuery;
use MetaMetrics\Historical\HistoricalResult;
use MetaMetrics\Historical\HistoricalService;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\Metric\Metric;

final readonly class HierarchyService
{
    public function __construct(private HistoricalService $historicalService)
    {
    }

    /** @param list<Metric> $metrics */
    public function historical(
        DateRange $dateRange,
        array $metrics = [],
        bool $daily = false,
    ): HierarchyResult {
        $campaigns = $this->historicalService->get(new HistoricalQuery(
            HistoricalLevel::CAMPAIGN,
            $dateRange,
            metrics: $metrics,
            daily: $daily,
        ));
        $adSets = $this->historicalService->get(new HistoricalQuery(
            HistoricalLevel::AD_SET,
            $dateRange,
            metrics: $metrics,
            daily: $daily,
        ));
        $ads = $this->historicalService->get(new HistoricalQuery(
            HistoricalLevel::AD,
            $dateRange,
            metrics: $metrics,
            daily: $daily,
        ));

        return $this->build($dateRange, $campaigns, $adSets, $ads);
    }

    /**
     * @param list<HistoricalResult> $campaigns
     * @param list<HistoricalResult> $adSets
     * @param list<HistoricalResult> $ads
     */
    private function build(
        DateRange $dateRange,
        array $campaigns,
        array $adSets,
        array $ads,
    ): HierarchyResult {
        $adsByAdSet = [];

        foreach ($ads as $ad) {
            $adsByAdSet[$this->parentKey($ad->adSetId(), $ad)][] = new HierarchyNode($ad);
        }

        $adSetNodes = [];
        $adSetsByCampaign = [];
        $attachedAdKeys = [];

        foreach ($adSets as $adSet) {
            $key = $this->resultKey($adSet);
            $childKey = $this->parentKey($adSet->adSetId(), $adSet);
            $children = $adsByAdSet[$childKey] ?? [];
            $adSetNodes[$key] = new HierarchyNode($adSet, $children);
            $adSetsByCampaign[$this->parentKey($adSet->campaignId(), $adSet)][] = $adSetNodes[$key];

            if ($children !== []) {
                $attachedAdKeys[$childKey] = true;
            }
        }

        $campaignNodes = [];
        $attachedAdSetKeys = [];

        foreach ($campaigns as $campaign) {
            $childKey = $this->parentKey($campaign->campaignId(), $campaign);
            $children = $adSetsByCampaign[$childKey] ?? [];
            $campaignNodes[] = new HierarchyNode($campaign, $children);

            foreach ($children as $child) {
                $attachedAdSetKeys[$this->resultKey($child->historicalResult())] = true;
            }
        }

        $unattachedAdSets = array_values(array_filter(
            $adSetNodes,
            fn (HierarchyNode $node): bool => !isset($attachedAdSetKeys[$this->resultKey($node->historicalResult())]),
        ));
        $unattachedAds = [];

        foreach ($adsByAdSet as $key => $nodes) {
            if (!isset($attachedAdKeys[$key])) {
                array_push($unattachedAds, ...$nodes);
            }
        }

        return new HierarchyResult($dateRange, $campaignNodes, $unattachedAdSets, $unattachedAds);
    }

    private function resultKey(HistoricalResult $result): string
    {
        return implode(':', [
            $result->entityId(),
            $result->insights()->dateStart(),
            $result->insights()->dateStop(),
        ]);
    }

    private function parentKey(?string $parentId, HistoricalResult $result): string
    {
        return implode(':', [
            $parentId ?? '',
            $result->insights()->dateStart(),
            $result->insights()->dateStop(),
        ]);
    }
}
