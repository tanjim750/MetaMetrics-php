<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Insights;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricAggregator;
use MetaMetrics\Insights\Metric\MetricRegistry;
use MetaMetrics\Insights\Normalizer\InsightsNormalizer;
use MetaMetrics\Insights\Parser\InsightsParser;
use PHPUnit\Framework\TestCase;

final class MetricAggregatorTest extends TestCase
{
    public function testItRecalculatesRatiosFromFoundationalTotals(): void
    {
        $metrics = [Metric::CTR, Metric::CPC, Metric::CPM, Metric::ROAS, Metric::REACH];
        $result = (new MetricAggregator())->aggregate([
            $this->row('2026-09-01', '20', '10', '100', '60', '40'),
            $this->row('2026-09-02', '80', '10', '900', '500', '260'),
        ], $metrics);

        self::assertNotNull($result);
        self::assertSame(2.0, $result->metric(Metric::CTR));
        self::assertSame(5.0, $result->metric(Metric::CPC));
        self::assertSame(100.0, $result->metric(Metric::CPM));
        self::assertSame(3.0, $result->metric(Metric::ROAS));
        self::assertNull($result->metric(Metric::REACH));
        self::assertSame('2026-09-01', $result->dateRange()->since());
        self::assertSame('2026-09-02', $result->dateRange()->until());
    }

    public function testSingleRowReachUsesMetaReportedValue(): void
    {
        $result = (new MetricAggregator())->aggregate([
            $this->row('2026-09-01', '20', '10', '100', '60', '40'),
        ], [Metric::REACH]);

        self::assertNotNull($result);
        self::assertSame(60.0, $result->metric(Metric::REACH));
    }

    public function testEmptyRowsDoNotManufactureAnAggregate(): void
    {
        self::assertNull((new MetricAggregator())->aggregate([], [Metric::SPEND]));
    }

    public function testOverlappingRowsAreRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('overlapping');

        (new MetricAggregator())->aggregate([
            $this->row('2026-09-01', '20', '10', '100', '60', '40'),
            $this->row('2026-09-01', '80', '10', '900', '500', '260'),
        ], [Metric::SPEND]);
    }

    public function testMixedEntitiesAreRejected(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('one entity');

        (new MetricAggregator())->aggregate([
            $this->row('2026-09-01', '20', '10', '100', '60', '40'),
            $this->row('2026-09-02', '80', '10', '900', '500', '260', 'campaign-2'),
        ], [Metric::SPEND]);
    }

    private function row(
        string $date,
        string $spend,
        string $clicks,
        string $impressions,
        string $reach,
        string $purchaseValue,
        string $campaignId = 'campaign-1',
    ): \MetaMetrics\Insights\InsightsDTO {
        $metrics = [Metric::CTR, Metric::CPC, Metric::CPM, Metric::ROAS, Metric::REACH];
        $foundational = (new MetricRegistry())->foundationalMetrics($metrics);
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => $campaignId,
            'campaign_name' => 'Campaign',
            'date_start' => $date,
            'date_stop' => $date,
            'spend' => $spend,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'reach' => $reach,
            'action_values' => [['action_type' => 'purchase', 'value' => $purchaseValue]],
        ], InsightsLevel::CAMPAIGN, $foundational);

        return (new InsightsNormalizer())->normalize($parsed, InsightsLevel::CAMPAIGN, $metrics);
    }
}
