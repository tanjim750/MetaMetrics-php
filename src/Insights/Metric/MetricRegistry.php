<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

use MetaMetrics\Exception\UnsupportedMetricException;

final class MetricRegistry
{
    /** @var array<string, MetricDefinition> */
    private array $definitions = [];

    /** @param list<MetricDefinition>|null $definitions */
    public function __construct(?array $definitions = null)
    {
        foreach ($definitions ?? self::defaultDefinitions() as $definition) {
            if (!$definition instanceof MetricDefinition) {
                throw new UnsupportedMetricException('Metric definitions must use MetricDefinition instances.');
            }

            $name = $definition->metric()->value;

            if (isset($this->definitions[$name])) {
                throw new UnsupportedMetricException(sprintf('Duplicate metric definition "%s".', $name));
            }

            $this->definitions[$name] = $definition;
        }

        $this->validateCatalog();
    }

    public function supports(Metric|string $metric): bool
    {
        $name = $metric instanceof Metric ? $metric->value : $metric;

        return isset($this->definitions[$name]);
    }

    public function fromName(string $name): Metric
    {
        $metric = Metric::tryFrom($name);

        if ($metric === null || !$this->supports($metric)) {
            throw new UnsupportedMetricException(sprintf('Unsupported metric "%s".', $name));
        }

        return $metric;
    }

    public function definition(Metric $metric): MetricDefinition
    {
        return $this->definitions[$metric->value]
            ?? throw new UnsupportedMetricException(sprintf('Unsupported metric "%s".', $metric->value));
    }

    /** @return list<MetricDefinition> */
    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    /** @return list<Metric> */
    public function foundationalMetrics(array $metrics): array
    {
        $resolved = [];

        foreach ($metrics as $metric) {
            $this->resolve($this->metric($metric), $resolved, []);
        }

        return array_values($resolved);
    }

    /** @return list<string> */
    public function metaFields(array $metrics): array
    {
        $fields = [];

        foreach ($this->foundationalMetrics($metrics) as $metric) {
            foreach ($this->definition($metric)->metaFields() as $field) {
                $fields[$field] = $field;
            }
        }

        return array_values($fields);
    }

    /** @return list<Metric> */
    public function dependencies(Metric $metric): array
    {
        return $this->definition($metric)->dependencies();
    }

    public function type(Metric $metric): MetricType
    {
        return $this->definition($metric)->type();
    }

    public function isDerived(Metric $metric): bool
    {
        return $this->definition($metric)->isDerived();
    }

    public function unit(Metric $metric): MetricUnit
    {
        return $this->definition($metric)->unit();
    }

    public function aggregation(Metric $metric): MetricAggregation
    {
        return $this->definition($metric)->aggregation();
    }

    public function requiresActions(Metric $metric): bool
    {
        foreach ($this->foundationalMetrics([$metric]) as $foundational) {
            if ($this->definition($foundational)->requiresActions()) {
                return true;
            }
        }

        return false;
    }

    public function requiresActionValues(Metric $metric): bool
    {
        foreach ($this->foundationalMetrics([$metric]) as $foundational) {
            if ($this->definition($foundational)->requiresActionValues()) {
                return true;
            }
        }

        return false;
    }

    public function sourceField(Metric $metric): ?string
    {
        return $this->definition($metric)->metaFields()[0] ?? null;
    }

    /** @param array<string, Metric> $resolved @param array<string, true> $resolving */
    private function resolve(Metric $metric, array &$resolved, array $resolving): void
    {
        if (isset($resolved[$metric->value])) {
            return;
        }

        if (isset($resolving[$metric->value])) {
            throw new UnsupportedMetricException(sprintf(
                'Circular metric dependency detected for "%s".',
                $metric->value,
            ));
        }

        $resolving[$metric->value] = true;

        foreach ($this->dependencies($metric) as $dependency) {
            $this->resolve($dependency, $resolved, $resolving);
        }

        if (!$this->isDerived($metric)) {
            $resolved[$metric->value] = $metric;
        }
    }

    private function metric(mixed $metric): Metric
    {
        if (!$metric instanceof Metric || !$this->supports($metric)) {
            throw new UnsupportedMetricException('Insights metrics must use a supported Metric enum.');
        }

        return $metric;
    }

    private function validateCatalog(): void
    {
        foreach (Metric::cases() as $metric) {
            if (!$this->supports($metric)) {
                throw new UnsupportedMetricException(sprintf(
                    'Metric catalog is missing definition "%s".',
                    $metric->value,
                ));
            }
        }

        if (count($this->definitions) !== count(Metric::cases())) {
            throw new UnsupportedMetricException('Metric catalog contains unsupported definitions.');
        }

        foreach (Metric::cases() as $metric) {
            foreach ($this->dependencies($metric) as $dependency) {
                if (!$this->supports($dependency)) {
                    throw new UnsupportedMetricException(sprintf(
                        'Metric "%s" depends on an unsupported metric.',
                        $metric->value,
                    ));
                }
            }

            $resolved = [];
            $this->resolve($metric, $resolved, []);
        }
    }

    /** @return list<MetricDefinition> */
    private static function defaultDefinitions(): array
    {
        return [
            new MetricDefinition(Metric::SPEND, MetricType::DIRECT, MetricUnit::MONEY, MetricAggregation::SUM, ['spend']),
            new MetricDefinition(Metric::PURCHASES, MetricType::ACTION, MetricUnit::COUNT, MetricAggregation::SUM, ['actions']),
            new MetricDefinition(
                Metric::COST_PER_PURCHASE,
                MetricType::DERIVED,
                MetricUnit::MONEY,
                MetricAggregation::RECALCULATE,
                dependencies: [Metric::SPEND, Metric::PURCHASES],
            ),
            new MetricDefinition(
                Metric::PURCHASE_VALUE,
                MetricType::ACTION_VALUE,
                MetricUnit::MONEY,
                MetricAggregation::SUM,
                ['action_values'],
            ),
            new MetricDefinition(
                Metric::ROAS,
                MetricType::DERIVED,
                MetricUnit::RATIO,
                MetricAggregation::RECALCULATE,
                dependencies: [Metric::SPEND, Metric::PURCHASE_VALUE],
            ),
            new MetricDefinition(Metric::IMPRESSIONS, MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::SUM, ['impressions']),
            new MetricDefinition(Metric::REACH, MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::META_ONLY, ['reach']),
            new MetricDefinition(Metric::CLICKS, MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::SUM, ['clicks']),
            new MetricDefinition(Metric::LINK_CLICKS, MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::SUM, ['inline_link_clicks']),
            new MetricDefinition(
                Metric::CPC,
                MetricType::DERIVED,
                MetricUnit::MONEY,
                MetricAggregation::RECALCULATE,
                dependencies: [Metric::SPEND, Metric::CLICKS],
            ),
            new MetricDefinition(
                Metric::CTR,
                MetricType::DERIVED,
                MetricUnit::PERCENT,
                MetricAggregation::RECALCULATE,
                dependencies: [Metric::CLICKS, Metric::IMPRESSIONS],
            ),
            new MetricDefinition(
                Metric::CPM,
                MetricType::DERIVED,
                MetricUnit::MONEY,
                MetricAggregation::RECALCULATE,
                dependencies: [Metric::SPEND, Metric::IMPRESSIONS],
            ),
        ];
    }
}
