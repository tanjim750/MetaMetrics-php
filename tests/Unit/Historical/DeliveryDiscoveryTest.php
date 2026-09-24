<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Historical;

use MetaMetrics\Historical\DeliveryDiscovery;
use MetaMetrics\Historical\DeliveryStatus;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsDTO;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\InsightsRawData;
use MetaMetrics\Insights\Metric\Metric;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeliveryDiscoveryTest extends TestCase
{
    /** @param array<string, float|null> $metrics */
    #[DataProvider('deliveryProvider')]
    public function testItClassifiesDeliveryEvidence(array $metrics, DeliveryStatus $expected): void
    {
        $discovery = new DeliveryDiscovery();

        $assessment = $discovery->assess($this->insights($metrics));

        self::assertSame($expected, $assessment->status());
    }

    /** @return iterable<string, array{array<string, float|null>, DeliveryStatus}> */
    public static function deliveryProvider(): iterable
    {
        yield 'impressions' => [['impressions' => 1.0, 'spend' => 0.0, 'reach' => 0.0], DeliveryStatus::DELIVERED];
        yield 'spend' => [['impressions' => 0.0, 'spend' => 0.01, 'reach' => 0.0], DeliveryStatus::DELIVERED];
        yield 'reach' => [['impressions' => 0.0, 'spend' => 0.0, 'reach' => 1.0], DeliveryStatus::DELIVERED];
        yield 'all zero' => [['impressions' => 0.0, 'spend' => 0.0, 'reach' => 0.0], DeliveryStatus::NOT_DELIVERED];
        yield 'missing' => [['impressions' => null, 'spend' => null, 'reach' => null], DeliveryStatus::INSUFFICIENT_EVIDENCE];
        yield 'conversion only' => [['purchases' => 12.0], DeliveryStatus::INSUFFICIENT_EVIDENCE];
    }

    public function testItReportsTheQualifyingEvidence(): void
    {
        $evidence = (new DeliveryDiscovery())->assess($this->insights([
            'impressions' => 1000.0,
            'spend' => 20.0,
            'reach' => 0.0,
        ]))->evidence();

        self::assertNotNull($evidence);
        self::assertSame(['impressions' => 1000.0, 'spend' => 20.0], $evidence->indicators());
        self::assertTrue($evidence->has(Metric::SPEND));
        self::assertNull($evidence->value(Metric::REACH));
    }

    /** @param array<string, float|null> $metrics */
    private function insights(array $metrics): InsightsDTO
    {
        return new InsightsDTO(
            entityId: 'campaign-1',
            entityName: 'Campaign',
            level: InsightsLevel::CAMPAIGN,
            campaignId: 'campaign-1',
            adSetId: null,
            adId: null,
            dateRange: new DateRange('2026-09-01', '2026-09-24'),
            metrics: $metrics,
            rawData: new InsightsRawData(null, null, null, null, null),
        );
    }
}
