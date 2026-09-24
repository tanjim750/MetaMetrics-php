<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnsupportedMetricException;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Query\Filter;

final readonly class HistoricalQuery
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
        private HistoricalLevel $level,
        private DateRange $dateRange,
        private ?string $parentId = null,
        array $metrics = [],
        private bool $daily = false,
        array $filters = [],
        private ?int $limit = null,
    ) {
        if ($this->parentId !== null && trim($this->parentId) === '') {
            throw new InvalidInputException('Historical parent scope ID must not be empty.');
        }

        if ($this->limit !== null && $this->limit < 1) {
            throw new InvalidInputException('Historical query limit must be greater than zero.');
        }

        $uniqueMetrics = [];

        foreach ($metrics as $metric) {
            if (!$metric instanceof Metric) {
                throw new UnsupportedMetricException('Historical metrics must use the Metric enum.');
            }

            $uniqueMetrics[$metric->value] = $metric;
        }

        foreach ($filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new InvalidInputException('Historical filters must be Filter instances.');
            }
        }

        $this->metrics = array_values($uniqueMetrics);
        $this->filters = array_values($filters);
    }

    public function level(): HistoricalLevel
    {
        return $this->level;
    }

    public function dateRange(): DateRange
    {
        return $this->dateRange;
    }

    public function parentId(): ?string
    {
        return $this->parentId;
    }

    /** @return list<Metric> */
    public function metrics(): array
    {
        return $this->metrics;
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
}
