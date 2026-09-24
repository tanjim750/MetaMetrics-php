<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Insights;

use MetaMetrics\Exception\UnsupportedMetricException;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricAggregation;
use MetaMetrics\Insights\Metric\MetricDefinition;
use MetaMetrics\Insights\Metric\MetricRegistry;
use MetaMetrics\Insights\Metric\MetricType;
use MetaMetrics\Insights\Metric\MetricUnit;
use PHPUnit\Framework\TestCase;

final class MetricRegistryTest extends TestCase
{
    public function testEveryMetricHasTypedValidatedMetadata(): void
    {
        $registry = new MetricRegistry();
        $expected = [
            Metric::SPEND->value => [MetricType::DIRECT, MetricUnit::MONEY, MetricAggregation::SUM, ['spend'], []],
            Metric::PURCHASES->value => [MetricType::ACTION, MetricUnit::COUNT, MetricAggregation::SUM, ['actions'], []],
            Metric::COST_PER_PURCHASE->value => [MetricType::DERIVED, MetricUnit::MONEY, MetricAggregation::RECALCULATE, [], [Metric::SPEND, Metric::PURCHASES]],
            Metric::PURCHASE_VALUE->value => [MetricType::ACTION_VALUE, MetricUnit::MONEY, MetricAggregation::SUM, ['action_values'], []],
            Metric::ROAS->value => [MetricType::DERIVED, MetricUnit::RATIO, MetricAggregation::RECALCULATE, [], [Metric::SPEND, Metric::PURCHASE_VALUE]],
            Metric::IMPRESSIONS->value => [MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::SUM, ['impressions'], []],
            Metric::REACH->value => [MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::META_ONLY, ['reach'], []],
            Metric::CLICKS->value => [MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::SUM, ['clicks'], []],
            Metric::LINK_CLICKS->value => [MetricType::DIRECT, MetricUnit::COUNT, MetricAggregation::SUM, ['inline_link_clicks'], []],
            Metric::CPC->value => [MetricType::DERIVED, MetricUnit::MONEY, MetricAggregation::RECALCULATE, [], [Metric::SPEND, Metric::CLICKS]],
            Metric::CTR->value => [MetricType::DERIVED, MetricUnit::PERCENT, MetricAggregation::RECALCULATE, [], [Metric::CLICKS, Metric::IMPRESSIONS]],
            Metric::CPM->value => [MetricType::DERIVED, MetricUnit::MONEY, MetricAggregation::RECALCULATE, [], [Metric::SPEND, Metric::IMPRESSIONS]],
        ];

        self::assertCount(count(Metric::cases()), $registry->definitions());

        foreach (Metric::cases() as $metric) {
            $definition = $registry->definition($metric);
            [$type, $unit, $aggregation, $fields, $dependencies] = $expected[$metric->value];

            self::assertSame($metric, $definition->metric());
            self::assertSame($type, $definition->type());
            self::assertSame($unit, $definition->unit());
            self::assertSame($aggregation, $definition->aggregation());
            self::assertSame($fields, $definition->metaFields());
            self::assertSame($dependencies, $definition->dependencies());
        }

        self::assertTrue($registry->requiresActions(Metric::PURCHASES));
        self::assertTrue($registry->requiresActions(Metric::COST_PER_PURCHASE));
        self::assertTrue($registry->requiresActionValues(Metric::PURCHASE_VALUE));
        self::assertTrue($registry->requiresActionValues(Metric::ROAS));
        self::assertFalse($registry->requiresActions(Metric::ROAS));
        self::assertFalse($registry->requiresActionValues(Metric::COST_PER_PURCHASE));
    }

    public function testStringBoundaryRejectsUnsupportedMetrics(): void
    {
        $registry = new MetricRegistry();

        self::assertSame(Metric::ROAS, $registry->fromName('roas'));

        $this->expectException(UnsupportedMetricException::class);
        $this->expectExceptionMessage('not_a_metric');

        $registry->fromName('not_a_metric');
    }

    public function testDuplicateDefinitionsAreRejected(): void
    {
        $definitions = (new MetricRegistry())->definitions();
        $definitions[] = $definitions[0];

        $this->expectException(UnsupportedMetricException::class);
        $this->expectExceptionMessage('Duplicate metric definition');

        new MetricRegistry($definitions);
    }

    public function testMissingDefinitionsAreRejected(): void
    {
        $definitions = (new MetricRegistry())->definitions();
        array_pop($definitions);

        $this->expectException(UnsupportedMetricException::class);
        $this->expectExceptionMessage('missing definition');

        new MetricRegistry($definitions);
    }

    public function testCircularDependenciesAreRejected(): void
    {
        $definitions = array_map(
            static fn (MetricDefinition $definition): MetricDefinition => match ($definition->metric()) {
                Metric::SPEND => new MetricDefinition(
                    Metric::SPEND,
                    MetricType::DERIVED,
                    MetricUnit::MONEY,
                    MetricAggregation::RECALCULATE,
                    dependencies: [Metric::CPC],
                ),
                default => $definition,
            },
            (new MetricRegistry())->definitions(),
        );

        $this->expectException(UnsupportedMetricException::class);
        $this->expectExceptionMessage('Circular metric dependency');

        new MetricRegistry($definitions);
    }

    public function testMalformedDefinitionIsRejected(): void
    {
        $this->expectException(UnsupportedMetricException::class);

        new MetricDefinition(
            Metric::CTR,
            MetricType::DERIVED,
            MetricUnit::PERCENT,
            MetricAggregation::RECALCULATE,
            metaFields: ['ctr'],
            dependencies: [Metric::CLICKS, Metric::IMPRESSIONS],
        );
    }
}
