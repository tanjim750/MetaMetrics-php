<?php

declare(strict_types=1);

namespace MetaMetrics\Insights;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnsupportedMetricException;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Query\Filter;

final readonly class InsightsQuery
{
    /** @var list<Metric> */
    private array $metrics;

    /** @var list<Filter> */
    private array $filters;

    /**
     * @param list<Metric> $metrics
     * @param list<Filter> $filters
     */
    public function __construct(
        private InsightsLevel $level,
        private DateRange $dateRange,
        array $metrics,
        private ?string $entityId = null,
        private bool $daily = false,
        array $filters = [],
        private ?int $limit = null,
        private bool $includeAdSetConfiguration = true,
    ) {
        if ($metrics === []) {
            throw new InvalidInputException('At least one Insights metric must be selected.');
        }

        $uniqueMetrics = [];

        foreach ($metrics as $metric) {
            if (!$metric instanceof Metric) {
                throw new UnsupportedMetricException('Insights metrics must use the Metric enum.');
            }

            $uniqueMetrics[$metric->value] = $metric;
        }

        foreach ($filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new InvalidInputException('Insights filters must be Filter instances.');
            }
        }

        if ($this->entityId !== null && trim($this->entityId) === '') {
            throw new InvalidInputException('Insights entity ID must not be empty.');
        }

        if ($this->limit !== null && $this->limit < 1) {
            throw new InvalidInputException('Insights query limit must be greater than zero.');
        }

        $this->metrics = array_values($uniqueMetrics);
        $this->filters = array_values($filters);
    }

    public function level(): InsightsLevel
    {
        return $this->level;
    }

    public function dateRange(): DateRange
    {
        return $this->dateRange;
    }

    /** @return list<Metric> */
    public function metrics(): array
    {
        return $this->metrics;
    }

    public function entityId(): ?string
    {
        return $this->entityId;
    }

    public function isDaily(): bool
    {
        return $this->daily;
    }

    /** @return list<Filter> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function limit(): ?int
    {
        return $this->limit;
    }

    public function includesAdSetConfiguration(): bool
    {
        return $this->includeAdSetConfiguration;
    }
}
