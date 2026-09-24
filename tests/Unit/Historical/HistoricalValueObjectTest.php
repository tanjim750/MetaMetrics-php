<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Historical;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Historical\DeliveryEvidence;
use MetaMetrics\Historical\HistoricalResult;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsDTO;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\InsightsRawData;
use MetaMetrics\Insights\Metric\Metric;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HistoricalValueObjectTest extends TestCase
{
    /** @param array<string, mixed> $indicators */
    #[DataProvider('invalidEvidenceProvider')]
    public function testDeliveryEvidenceRejectsInvalidIndicators(array $indicators): void
    {
        $this->expectException(InvalidInputException::class);

        new DeliveryEvidence($indicators);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidEvidenceProvider(): iterable
    {
        yield 'empty' => [[]];
        yield 'zero' => [['spend' => 0.0]];
        yield 'negative' => [['impressions' => -1.0]];
        yield 'unknown metric' => [['unknown' => 1.0]];
        yield 'not numeric' => [['reach' => '10']];
    }

    public function testHistoricalResultRejectsAccountInsights(): void
    {
        $this->expectException(InvalidInputException::class);

        new HistoricalResult(
            $this->range(),
            new DeliveryEvidence(['spend' => 1.0]),
            $this->insights(InsightsLevel::ACCOUNT, $this->range()),
        );
    }

    public function testHistoricalResultRejectsInsightsOutsideRequestedPeriod(): void
    {
        $this->expectException(InvalidInputException::class);

        new HistoricalResult(
            $this->range(),
            new DeliveryEvidence(['spend' => 1.0]),
            $this->insights(InsightsLevel::CAMPAIGN, new DateRange('2026-08-31', '2026-09-01')),
        );
    }

    public function testHistoricalResultRejectsEvidenceThatDoesNotMatchInsights(): void
    {
        $this->expectException(InvalidInputException::class);

        new HistoricalResult(
            $this->range(),
            new DeliveryEvidence(['spend' => 2.0]),
            $this->insights(InsightsLevel::CAMPAIGN, $this->range()),
        );
    }

    private function range(): DateRange
    {
        return new DateRange('2026-09-01', '2026-09-24');
    }

    private function insights(InsightsLevel $level, DateRange $dateRange): InsightsDTO
    {
        return new InsightsDTO(
            entityId: 'entity-1',
            entityName: 'Entity',
            level: $level,
            campaignId: $level === InsightsLevel::ACCOUNT ? null : 'entity-1',
            adSetId: null,
            adId: null,
            dateRange: $dateRange,
            metrics: [Metric::SPEND->value => 1.0],
            rawData: new InsightsRawData('1', null, null, null, null),
        );
    }
}
