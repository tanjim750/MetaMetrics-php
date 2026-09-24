<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\DateRange;

use DateTimeZone;
use Exception;
use MetaMetrics\Exception\InvalidInputException;

final readonly class AccountTimezone
{
    private DateTimeZone $timezone;

    public function __construct(private string $name)
    {
        try {
            $this->timezone = new DateTimeZone($this->name);
        } catch (Exception) {
            throw new InvalidInputException('Account timezone must be a valid timezone name.');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }
}
