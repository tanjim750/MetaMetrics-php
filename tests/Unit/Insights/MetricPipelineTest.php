<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Insights;

use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricCalculator;
use MetaMetrics\Insights\Metric\MetricRegistry;
use MetaMetrics\Insights\Normalizer\InsightsNormalizer;
use MetaMetrics\Insights\Parser\InsightsParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetricPipelineTest extends TestCase
{
    public function testRegistryResolvesOnlyRequiredFoundationalFields(): void
    {
        $registry = new MetricRegistry();

        self::assertSame(
            [Metric::SPEND, Metric::PURCHASE_VALUE, Metric::CLICKS, Metric::IMPRESSIONS],
            $registry->foundationalMetrics([Metric::ROAS, Metric::CTR]),
        );
        self::assertSame(
            ['spend', 'action_values', 'clicks', 'impressions'],
            $registry->metaFields([Metric::ROAS, Metric::CTR]),
        );
    }

    public function testItCalculatesDerivedMetricsFromFoundationalValues(): void
    {
        $values = [
            Metric::SPEND->value => 100.0,
            Metric::PURCHASES->value => 4.0,
            Metric::PURCHASE_VALUE->value => 300.0,
            Metric::CLICKS->value => 20.0,
            Metric::IMPRESSIONS->value => 1000.0,
        ];
        $calculator = new MetricCalculator();

        self::assertSame(25.0, $calculator->calculate(Metric::COST_PER_PURCHASE, $values));
        self::assertSame(3.0, $calculator->calculate(Metric::ROAS, $values));
        self::assertSame(5.0, $calculator->calculate(Metric::CPC, $values));
        self::assertSame(2.0, $calculator->calculate(Metric::CTR, $values));
        self::assertSame(100.0, $calculator->calculate(Metric::CPM, $values));
    }

    public function testItPreservesMissingValuesAndAvoidsZeroDivision(): void
    {
        $calculator = new MetricCalculator();

        self::assertNull($calculator->calculate(Metric::ROAS, [
            Metric::PURCHASE_VALUE->value => 0.0,
            Metric::SPEND->value => 0.0,
        ]));
        self::assertNull($calculator->calculate(Metric::CPC, [
            Metric::SPEND->value => 100.0,
            Metric::CLICKS->value => 0.0,
        ]));
        self::assertNull($calculator->calculate(Metric::COST_PER_PURCHASE, [
            Metric::SPEND->value => 100.0,
            Metric::PURCHASES->value => null,
        ]));
        self::assertNull($calculator->calculate(Metric::COST_PER_PURCHASE, [
            Metric::SPEND->value => 100.0,
            Metric::PURCHASES->value => 0.0,
        ]));
        self::assertNull($calculator->calculate(Metric::CPC, [
            Metric::SPEND->value => null,
            Metric::CLICKS->value => 10.0,
        ]));
        self::assertNull($calculator->calculate(Metric::CTR, [
            Metric::CLICKS->value => 10.0,
            Metric::IMPRESSIONS->value => 0.0,
        ]));
        self::assertNull($calculator->calculate(Metric::CPM, [
            Metric::SPEND->value => 100.0,
            Metric::IMPRESSIONS->value => 0.0,
        ]));
        self::assertSame(0.0, $calculator->calculate(Metric::CTR, [
            Metric::CLICKS->value => 0.0,
            Metric::IMPRESSIONS->value => 1000.0,
        ]));
        self::assertSame(0.0, $calculator->calculate(Metric::ROAS, [
            Metric::PURCHASE_VALUE->value => 0.0,
            Metric::SPEND->value => 100.0,
        ]));
    }

    public function testCalculatorRejectsMalformedNormalizedInput(): void
    {
        $this->expectException(InvalidInputException::class);

        (new MetricCalculator())->calculate(Metric::CPC, [
            Metric::SPEND->value => '100',
            Metric::CLICKS->value => 10.0,
        ]);
    }

    public function testCountAndValueUseOneSharedPurchaseActionType(): void
    {
        $metrics = [Metric::PURCHASES, Metric::PURCHASE_VALUE, Metric::COST_PER_PURCHASE, Metric::ROAS];
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'campaign_name' => 'Sales',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '100',
            'actions' => [
                ['action_type' => 'omni_purchase', 'value' => '10'],
                ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '8'],
            ],
            'action_values' => [
                ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '800'],
            ],
        ], InsightsLevel::CAMPAIGN, (new MetricRegistry())->foundationalMetrics($metrics));
        $insights = (new InsightsNormalizer())->normalize($parsed, InsightsLevel::CAMPAIGN, $metrics);

        self::assertSame(8.0, $insights->purchases());
        self::assertSame(800.0, $insights->purchaseValue());
        self::assertSame(12.5, $insights->costPerPurchase());
        self::assertSame(8.0, $insights->roas());
    }

    public function testIncompatiblePurchaseActionTypesRemainUnavailable(): void
    {
        $metrics = [Metric::PURCHASES, Metric::PURCHASE_VALUE];
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'actions' => [['action_type' => 'omni_purchase', 'value' => '10']],
            'action_values' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '800']],
        ], InsightsLevel::CAMPAIGN, $metrics);

        self::assertNull($parsed['foundational'][Metric::PURCHASES->value]);
        self::assertNull($parsed['foundational'][Metric::PURCHASE_VALUE->value]);
    }

    public function testMissingPurchaseCountDoesNotEraseAvailablePurchaseValue(): void
    {
        $metrics = [Metric::PURCHASES, Metric::PURCHASE_VALUE];
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'actions' => null,
            'action_values' => [['action_type' => 'purchase', 'value' => '800']],
        ], InsightsLevel::CAMPAIGN, $metrics);

        self::assertNull($parsed['foundational'][Metric::PURCHASES->value]);
        self::assertSame(800.0, $parsed['foundational'][Metric::PURCHASE_VALUE->value]);
    }

    public function testMissingPurchaseValueDoesNotEraseAvailablePurchaseCount(): void
    {
        $metrics = [Metric::PURCHASES, Metric::PURCHASE_VALUE];
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'actions' => [['action_type' => 'purchase', 'value' => '2']],
            'action_values' => null,
        ], InsightsLevel::CAMPAIGN, $metrics);

        self::assertSame(2.0, $parsed['foundational'][Metric::PURCHASES->value]);
        self::assertNull($parsed['foundational'][Metric::PURCHASE_VALUE->value]);
    }

    public function testItPreservesAdHierarchyAndNormalizesNumericStrings(): void
    {
        $metrics = [Metric::SPEND, Metric::IMPRESSIONS];
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'adset_id' => 'adset-1',
            'ad_id' => 'ad-1',
            'ad_name' => 'Creative One',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-01',
            'spend' => '12.345678',
            'impressions' => '1000',
        ], InsightsLevel::AD, $metrics);
        $insights = (new InsightsNormalizer())->normalize($parsed, InsightsLevel::AD, $metrics);

        self::assertSame('ad-1', $insights->entityId());
        self::assertSame('campaign-1', $insights->campaignId());
        self::assertSame('adset-1', $insights->adSetId());
        self::assertSame('ad-1', $insights->adId());
        self::assertSame(12.345678, $insights->spend());
        self::assertSame(1000.0, $insights->impressions());
    }

    public function testItPreservesAdSetHierarchy(): void
    {
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'adset_id' => 'adset-1',
            'adset_name' => 'Prospecting',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '50',
        ], InsightsLevel::AD_SET, [Metric::SPEND]);
        $insights = (new InsightsNormalizer())->normalize(
            $parsed,
            InsightsLevel::AD_SET,
            [Metric::SPEND],
        );

        self::assertSame('adset-1', $insights->entityId());
        self::assertSame('campaign-1', $insights->campaignId());
        self::assertSame('adset-1', $insights->adSetId());
        self::assertNull($insights->adId());
    }

    public function testCurrentStatusAndCreationDateDoNotAffectRequestedPeriodInsights(): void
    {
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'campaign_name' => 'Historical Campaign',
            'status' => 'PAUSED',
            'created_time' => '2026-08-10T00:00:00+0000',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '100',
            'impressions' => '1000',
        ], InsightsLevel::CAMPAIGN, [Metric::SPEND, Metric::IMPRESSIONS]);
        $insights = (new InsightsNormalizer())->normalize(
            $parsed,
            InsightsLevel::CAMPAIGN,
            [Metric::SPEND, Metric::IMPRESSIONS],
        );

        self::assertSame('2026-09-01', $insights->dateStart());
        self::assertSame('2026-09-24', $insights->dateStop());
        self::assertSame(100.0, $insights->spend());
        self::assertSame(1000.0, $insights->impressions());
    }

    public function testAnExplicitActionListWithoutPurchasesNormalizesToZero(): void
    {
        $parsed = (new InsightsParser())->parse([
            'account_id' => '123',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'actions' => [['action_type' => 'link_click', 'value' => '25']],
        ], InsightsLevel::ACCOUNT, [Metric::PURCHASES]);

        self::assertSame(0.0, $parsed['foundational'][Metric::PURCHASES->value]);
    }

    public function testItPreservesCompleteRawCprInputs(): void
    {
        $actions = [[
            'action_type' => 'offsite_conversion.fb_pixel_purchase',
            'value' => '4',
            '1d_view' => '1',
            '7d_click' => '3',
        ]];
        $costPerActionType = [[
            'action_type' => 'offsite_conversion.fb_pixel_purchase',
            'value' => '25.125',
        ]];
        $promotedObject = [
            'custom_conversion_id' => 'custom-1',
            'product_set_id' => 'product-set-1',
            'extra_meta_field' => ['nested' => true],
        ];
        $parsed = (new InsightsParser())->parse([
            'campaign_id' => 'campaign-1',
            'adset_id' => 'adset-1',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '100.5000',
            'actions' => $actions,
            'cost_per_action_type' => $costPerActionType,
        ], InsightsLevel::AD_SET, [Metric::SPEND], [
            'id' => 'adset-1',
            'optimization_goal' => 'OFFSITE_CONVERSIONS',
            'promoted_object' => $promotedObject,
        ]);
        $insights = (new InsightsNormalizer())->normalize(
            $parsed,
            InsightsLevel::AD_SET,
            [Metric::SPEND],
        );

        self::assertSame('100.5000', $insights->rawData()->spend());
        self::assertSame($actions, $insights->rawData()->actions());
        self::assertSame($costPerActionType, $insights->rawData()->costPerActionType());
        self::assertSame('OFFSITE_CONVERSIONS', $insights->rawData()->optimizationGoal());
        self::assertSame($promotedObject, $insights->rawData()->promotedObject());
    }

    #[DataProvider('malformedNumericProvider')]
    public function testItRejectsMalformedNumericData(mixed $value): void
    {
        $this->expectException(UnexpectedResponseException::class);

        (new InsightsParser())->parse([
            'account_id' => '123',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => $value,
        ], InsightsLevel::ACCOUNT, [Metric::SPEND]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function malformedNumericProvider(): iterable
    {
        yield 'text' => ['not-a-number'];
        yield 'empty string' => [''];
        yield 'array' => [['100']];
        yield 'object' => [new \stdClass()];
    }
}
