<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\DateRange;

use DateTimeImmutable;
use DateTimeZone;
use MetaMetrics\Exception\InvalidInputException;

final readonly class DateRange
{
    public function __construct(
        private string $since,
        private string $until,
    ) {
        $since = $this->parse($this->since, 'since');
        $until = $this->parse($this->until, 'until');

        if ($since > $until) {
            throw new InvalidInputException('Insights date range since must be before or equal to until.');
        }
    }

    public function since(): string
    {
        return $this->since;
    }

    public function until(): string
    {
        return $this->until;
    }

    /** @return array{since: string, until: string} */
    public function toArray(): array
    {
        return ['since' => $this->since, 'until' => $this->until];
    }

    private function parse(string $date, string $boundary): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidInputException(sprintf('Insights %s date must use a valid YYYY-MM-DD value.', $boundary));
        }

        return $parsed;
    }
}
