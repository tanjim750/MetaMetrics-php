<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Insights;

use DateTimeImmutable;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Insights\DateRange\AccountTimezone;
use MetaMetrics\Insights\DateRange\DatePreset;
use MetaMetrics\Insights\DateRange\DateRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    public function testItPreservesAnExplicitValidRange(): void
    {
        $range = new DateRange('2026-09-01', '2026-09-24');

        self::assertSame('2026-09-01', $range->since());
        self::assertSame('2026-09-24', $range->until());
        self::assertSame(['since' => '2026-09-01', 'until' => '2026-09-24'], $range->toArray());
    }

    #[DataProvider('invalidRangeProvider')]
    public function testItRejectsInvalidRanges(string $since, string $until): void
    {
        $this->expectException(InvalidInputException::class);

        new DateRange($since, $until);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidRangeProvider(): iterable
    {
        yield 'reversed' => ['2026-09-24', '2026-09-01'];
        yield 'impossible date' => ['2026-02-30', '2026-03-01'];
        yield 'wrong format' => ['09/01/2026', '2026-09-24'];
        yield 'missing boundary' => ['', '2026-09-24'];
    }

    public function testPresetsUseTheAdAccountTimezone(): void
    {
        $accountTimezone = new AccountTimezone('Europe/London');
        $nowInDhaka = new DateTimeImmutable('2026-10-01T00:30:00+06:00');

        $today = DatePreset::TODAY->resolve($accountTimezone, $nowInDhaka);
        $currentMonth = DatePreset::CURRENT_MONTH->resolve($accountTimezone, $nowInDhaka);
        $previousMonth = DatePreset::PREVIOUS_MONTH->resolve($accountTimezone, $nowInDhaka);

        self::assertSame(['since' => '2026-09-30', 'until' => '2026-09-30'], $today->toArray());
        self::assertSame(['since' => '2026-09-01', 'until' => '2026-09-30'], $currentMonth->toArray());
        self::assertSame(['since' => '2026-08-01', 'until' => '2026-08-31'], $previousMonth->toArray());
    }

    public function testItRejectsAnInvalidAccountTimezone(): void
    {
        $this->expectException(InvalidInputException::class);

        new AccountTimezone('Not/A-Timezone');
    }
}
