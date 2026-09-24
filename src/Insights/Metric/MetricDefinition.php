<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

use MetaMetrics\Exception\UnsupportedMetricException;

final readonly class MetricDefinition
{
    /** @var list<string> */
    private array $metaFields;

    /** @var list<Metric> */
    private array $dependencies;

    /**
     * @param list<string> $metaFields
     * @param list<Metric> $dependencies
     */
    public function __construct(
        private Metric $metric,
        private MetricType $type,
        private MetricUnit $unit,
        private MetricAggregation $aggregation,
        array $metaFields = [],
        array $dependencies = [],
    ) {
        if (count($metaFields) !== count(array_unique($metaFields))) {
            throw new UnsupportedMetricException(sprintf(
                'Metric "%s" contains duplicate Meta fields.',
                $this->metric->value,
            ));
        }

        foreach ($metaFields as $field) {
            if (!is_string($field) || preg_match('/\A[a-z][a-z0-9_]*\z/D', $field) !== 1) {
                throw new UnsupportedMetricException(sprintf(
                    'Metric "%s" contains an invalid Meta field.',
                    $this->metric->value,
                ));
            }
        }

        foreach ($dependencies as $dependency) {
            if (!$dependency instanceof Metric) {
                throw new UnsupportedMetricException(sprintf(
                    'Metric "%s" contains an invalid dependency.',
                    $this->metric->value,
                ));
            }
        }

        if (count($dependencies) !== count(array_unique(array_map(
            static fn (Metric $dependency): string => $dependency->value,
            $dependencies,
        )))) {
            throw new UnsupportedMetricException(sprintf(
                'Metric "%s" contains duplicate dependencies.',
                $this->metric->value,
            ));
        }

        if ($this->type === MetricType::DERIVED && ($metaFields !== [] || $dependencies === [])) {
            throw new UnsupportedMetricException(sprintf(
                'Derived metric "%s" requires dependencies and no direct Meta fields.',
                $this->metric->value,
            ));
        }

        if ($this->type !== MetricType::DERIVED && ($metaFields === [] || $dependencies !== [])) {
            throw new UnsupportedMetricException(sprintf(
                'Foundational metric "%s" requires Meta fields and no dependencies.',
                $this->metric->value,
            ));
        }

        if (($this->type === MetricType::DERIVED)
            !== ($this->aggregation === MetricAggregation::RECALCULATE)) {
            throw new UnsupportedMetricException(sprintf(
                'Metric "%s" contains incompatible type and aggregation semantics.',
                $this->metric->value,
            ));
        }

        $this->metaFields = array_values($metaFields);
        $this->dependencies = array_values($dependencies);
    }

    public function metric(): Metric { return $this->metric; }
    public function type(): MetricType { return $this->type; }
    public function unit(): MetricUnit { return $this->unit; }
    public function aggregation(): MetricAggregation { return $this->aggregation; }

    /** @return list<string> */
    public function metaFields(): array { return $this->metaFields; }

    /** @return list<Metric> */
    public function dependencies(): array { return $this->dependencies; }

    public function isDerived(): bool { return $this->type === MetricType::DERIVED; }
    public function requiresActions(): bool { return $this->type === MetricType::ACTION; }
    public function requiresActionValues(): bool { return $this->type === MetricType::ACTION_VALUE; }
}
