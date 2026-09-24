<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

use MetaMetrics\Exception\InvalidInputException;

final readonly class MetricCalculator
{
    public function __construct(private MetricRegistry $registry = new MetricRegistry())
    {
    }

    /**
     * @param list<Metric> $requested
     * @param array<string, float|null> $foundational
     * @return array<string, float|null>
     */
    public function calculateAll(array $requested, array $foundational): array
    {
        $values = [];

        foreach ($requested as $metric) {
            $values[$metric->value] = $this->calculate($metric, $foundational);
        }

        return $values;
    }

    /** @param array<string, float|null> $values */
    public function calculate(Metric $metric, array $values): ?float
    {
        $this->registry->definition($metric);

        if (!$this->registry->isDerived($metric)) {
            return $this->value($values, $metric);
        }

        return match ($metric) {
            Metric::COST_PER_PURCHASE => $this->divide(
                $this->value($values, Metric::SPEND),
                $this->value($values, Metric::PURCHASES),
            ),
            Metric::ROAS => $this->divide(
                $this->value($values, Metric::PURCHASE_VALUE),
                $this->value($values, Metric::SPEND),
            ),
            Metric::CPC => $this->divide(
                $this->value($values, Metric::SPEND),
                $this->value($values, Metric::CLICKS),
            ),
            Metric::CTR => $this->multiply($this->divide(
                $this->value($values, Metric::CLICKS),
                $this->value($values, Metric::IMPRESSIONS),
            ), 100),
            Metric::CPM => $this->multiply($this->divide(
                $this->value($values, Metric::SPEND),
                $this->value($values, Metric::IMPRESSIONS),
            ), 1000),
            default => null,
        };
    }

    /** @param array<string, float|null> $values */
    private function value(array $values, Metric $metric): ?float
    {
        if (!array_key_exists($metric->value, $values) || $values[$metric->value] === null) {
            return null;
        }

        $value = $values[$metric->value];

        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new InvalidInputException(sprintf(
                'Normalized metric "%s" must be a finite number or null.',
                $metric->value,
            ));
        }

        return (float) $value;
    }

    private function divide(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || $denominator == 0.0) {
            return null;
        }

        $result = $numerator / $denominator;

        if (!is_finite($result)) {
            throw new InvalidInputException('Derived metric calculation produced a non-finite value.');
        }

        return $result;
    }

    private function multiply(?float $value, float $multiplier): ?float
    {
        if ($value === null) {
            return null;
        }

        $result = $value * $multiplier;

        if (!is_finite($result)) {
            throw new InvalidInputException('Derived metric calculation produced a non-finite value.');
        }

        return $result;
    }
}
