<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\AdSet;

use MetaMetrics\AdSet\AdSetNormalizer;
use MetaMetrics\Exception\UnexpectedResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdSetNormalizerTest extends TestCase
{
    public function testItNormalizesAdSetMetadata(): void
    {
        $adSet = (new AdSetNormalizer())->normalize([
            'id' => 'adset-1',
            'name' => 'Conversions',
            'campaign_id' => 'campaign-1',
            'status' => 'ACTIVE',
            'optimization_goal' => 'OFFSITE_CONVERSIONS',
            'billing_event' => 'IMPRESSIONS',
            'daily_budget' => '1000',
            'start_time' => '2026-09-01T08:30:00+0600',
        ]);

        self::assertSame('adset-1', $adSet->id());
        self::assertSame('campaign-1', $adSet->campaignId());
        self::assertSame('OFFSITE_CONVERSIONS', $adSet->optimizationGoal());
        self::assertSame('1000', $adSet->dailyBudget());
        self::assertSame('2026-09-01T02:30:00+00:00', $adSet->startTime()?->format(DATE_ATOM));
    }

    #[DataProvider('malformedAdSetProvider')]
    public function testItRejectsMalformedAdSets(array $data, string $field): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($field);

        (new AdSetNormalizer())->normalize($data);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function malformedAdSetProvider(): iterable
    {
        yield 'missing ID' => [['name' => 'Set', 'campaign_id' => 'c'], 'id'];
        yield 'missing campaign ID' => [['id' => '1', 'name' => 'Set'], 'campaign_id'];
        yield 'invalid budget' => [[
            'id' => '1', 'name' => 'Set', 'campaign_id' => 'c', 'daily_budget' => 1000,
        ], 'daily_budget'];
        yield 'invalid timestamp' => [[
            'id' => '1', 'name' => 'Set', 'campaign_id' => 'c', 'start_time' => 'bad',
        ], 'start_time'];
    }
}
