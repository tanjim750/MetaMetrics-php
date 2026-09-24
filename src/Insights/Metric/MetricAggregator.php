<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnsupportedMetricException;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsDTO;

final readonly class MetricAggregator
{
    public function __construct(
        private MetricRegistry $registry = new MetricRegistry(),
        private MetricCalculator $calculator = new MetricCalculator(),
    ) {
    }

    /**
     * @param list<InsightsDTO> $rows
     * @param list<Metric> $requestedMetrics
     */
    public function aggregate(array $rows, array $requestedMetrics): ?MetricAggregationResult
    {
        if ($requestedMetrics === []) {
            throw new InvalidInputException('Metric aggregation requires at least one requested metric.');
        }

        foreach ($requestedMetrics as $metric) {
            if (!$metric instanceof Metric) {
                throw new UnsupportedMetricException('Aggregated metrics must use the Metric enum.');
            }
        }

        if ($rows === []) {
            return null;
        }

        foreach ($rows as $row) {
            if (!$row instanceof InsightsDTO) {
                throw new InvalidInputException('Metric aggregation rows must be InsightsDTO instances.');
            }
        }

        usort($rows, static fn (InsightsDTO $left, InsightsDTO $right): int => [
            $left->dateStart(),
            $left->dateStop(),
        ] <=> [
            $right->dateStart(),
            $right->dateStop(),
        ]);

        $this->validateRows($rows);
        $foundational = [];

        foreach ($this->registry->foundationalMetrics($requestedMetrics) as $metric) {
            $foundational[$metric->value] = match ($this->registry->aggregation($metric)) {
                MetricAggregation::SUM => $this->sum($rows, $metric),
                MetricAggregation::META_ONLY => $this->aggregateMetaOnly($rows, $metric),
                MetricAggregation::RECALCULATE => throw new InvalidInputException(
                    'Derived metrics cannot be aggregated as foundational values.',
                ),
            };
        }

        $first = $rows[0];
        $last = $rows[array_key_last($rows)];

        return new MetricAggregationResult(
            entityId: $first->entityId(),
            level: $first->level(),
            dateRange: new DateRange($first->dateStart(), $last->dateStop()),
            metrics: $this->calculator->calculateAll($requestedMetrics, $foundational),
        );
    }

    /** @param non-empty-list<InsightsDTO> $rows */
    private function validateRows(array $rows): void
    {
        $first = $rows[0];
        $previous = null;

        foreach ($rows as $row) {
            if ($row->entityId() !== $first->entityId() || $row->level() !== $first->level()) {
                throw new InvalidInputException('Metric aggregation rows must represent one entity and level.');
            }

            if ($previous !== null && $row->dateStart() <= $previous->dateStop()) {
                throw new InvalidInputException('Metric aggregation rows must not have overlapping reporting periods.');
            }

            $previous = $row;
        }
    }

    /** @param non-empty-list<InsightsDTO> $rows */
    private function sum(array $rows, Metric $metric): ?float
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $value = $row->foundationalMetric($metric);

            if ($value === null) {
                return null;
            }

            $total += $value;

            if (!is_finite($total)) {
                throw new InvalidInputException('Metric aggregation produced a non-finite total.');
            }
        }

        return $total;
    }

    /** @param non-empty-list<InsightsDTO> $rows */
    private function aggregateMetaOnly(array $rows, Metric $metric): ?float
    {
        return count($rows) === 1
            ? $rows[0]->foundationalMetric($metric)
            : null;
    }
}
