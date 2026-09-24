<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Campaign;

use MetaMetrics\Campaign\CampaignNormalizer;
use MetaMetrics\Exception\UnexpectedResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CampaignNormalizerTest extends TestCase
{
    public function testItNormalizesCampaignMetadata(): void
    {
        $campaign = (new CampaignNormalizer())->normalize([
            'id' => '120000000000001',
            'name' => 'September Sales',
            'status' => 'PAUSED',
            'configured_status' => 'PAUSED',
            'effective_status' => 'WITH_ISSUES',
            'objective' => 'OUTCOME_SALES',
            'created_time' => '2026-09-01T08:30:00+0600',
            'updated_time' => '2026-09-02T10:45:00+0600',
        ]);

        self::assertSame('120000000000001', $campaign->id());
        self::assertSame('September Sales', $campaign->name());
        self::assertSame('PAUSED', $campaign->status());
        self::assertSame('PAUSED', $campaign->configuredStatus());
        self::assertSame('WITH_ISSUES', $campaign->effectiveStatus());
        self::assertSame('OUTCOME_SALES', $campaign->objective());
        self::assertSame('2026-09-01T02:30:00+00:00', $campaign->createdTime()?->format(DATE_ATOM));
        self::assertSame('2026-09-02T04:45:00+00:00', $campaign->updatedTime()?->format(DATE_ATOM));
    }

    public function testItLeavesOptionalFieldsMissingInsteadOfInventingValues(): void
    {
        $campaign = (new CampaignNormalizer())->normalize([
            'id' => '120000000000002',
            'name' => 'Minimal Campaign',
        ]);

        self::assertNull($campaign->status());
        self::assertNull($campaign->configuredStatus());
        self::assertNull($campaign->effectiveStatus());
        self::assertNull($campaign->objective());
        self::assertNull($campaign->createdTime());
        self::assertNull($campaign->updatedTime());
    }

    #[DataProvider('malformedCampaignProvider')]
    public function testItRejectsMalformedCampaignData(array $data, string $field): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($field);

        (new CampaignNormalizer())->normalize($data);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function malformedCampaignProvider(): iterable
    {
        yield 'missing ID' => [['name' => 'Campaign'], 'id'];
        yield 'non-string ID' => [['id' => 123, 'name' => 'Campaign'], 'id'];
        yield 'missing name' => [['id' => '123'], 'name'];
        yield 'invalid optional status' => [['id' => '123', 'name' => 'Campaign', 'status' => []], 'status'];
        yield 'invalid created time' => [['id' => '123', 'name' => 'Campaign', 'created_time' => 'not-a-date'], 'created_time'];
    }
}
