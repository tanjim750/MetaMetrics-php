<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\DateRange;

use DateTimeImmutable;

enum DatePreset: string
{
    case TODAY = 'today';
    case YESTERDAY = 'yesterday';
    case CURRENT_MONTH = 'current_month';
    case PREVIOUS_MONTH = 'previous_month';

    public function resolve(AccountTimezone $accountTimezone, ?DateTimeImmutable $now = null): DateRange
    {
        $timezone = $accountTimezone->timezone();
        $today = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone)->setTime(0, 0);

        return match ($this) {
            self::TODAY => self::range($today, $today),
            self::YESTERDAY => self::range($today->modify('-1 day'), $today->modify('-1 day')),
            self::CURRENT_MONTH => self::range($today->modify('first day of this month'), $today),
            self::PREVIOUS_MONTH => self::range(
                $today->modify('first day of previous month'),
                $today->modify('last day of previous month'),
            ),
        };
    }

    private static function range(DateTimeImmutable $since, DateTimeImmutable $until): DateRange
    {
        return new DateRange($since->format('Y-m-d'), $until->format('Y-m-d'));
    }
}
