<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Ad;

use MetaMetrics\Ad\AdNormalizer;
use MetaMetrics\Exception\UnexpectedResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdNormalizerTest extends TestCase
{
    public function testItNormalizesAdMetadata(): void
    {
        $ad = (new AdNormalizer())->normalize([
            'id' => 'ad-1',
            'name' => 'Primary Ad',
            'adset_id' => 'adset-1',
            'campaign_id' => 'campaign-1',
            'status' => 'ACTIVE',
            'configured_status' => 'ACTIVE',
            'effective_status' => 'PAUSED',
            'created_time' => '2026-09-01T08:30:00+0600',
            'updated_time' => '2026-09-02T10:45:00+0600',
        ]);

        self::assertSame('ad-1', $ad->id());
        self::assertSame('adset-1', $ad->adSetId());
        self::assertSame('campaign-1', $ad->campaignId());
        self::assertSame('PAUSED', $ad->effectiveStatus());
        self::assertSame('2026-09-01T02:30:00+00:00', $ad->createdTime()?->format(DATE_ATOM));
    }

    #[DataProvider('malformedAdProvider')]
    public function testItRejectsMalformedAds(array $data, string $field): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($field);

        (new AdNormalizer())->normalize($data);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function malformedAdProvider(): iterable
    {
        yield 'missing ID' => [['name' => 'Ad', 'adset_id' => 'a', 'campaign_id' => 'c'], 'id'];
        yield 'missing Ad Set ID' => [['id' => '1', 'name' => 'Ad', 'campaign_id' => 'c'], 'adset_id'];
        yield 'invalid status' => [[
            'id' => '1', 'name' => 'Ad', 'adset_id' => 'a', 'campaign_id' => 'c', 'status' => [],
        ], 'status'];
        yield 'invalid timestamp' => [[
            'id' => '1', 'name' => 'Ad', 'adset_id' => 'a', 'campaign_id' => 'c', 'created_time' => 'bad',
        ], 'created_time'];
    }
}
