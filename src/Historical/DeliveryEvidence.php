<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Insights\Metric\Metric;

final readonly class DeliveryEvidence
{
    /** @var non-empty-array<string, float> */
    private array $indicators;

    /** @param non-empty-array<string, float> $indicators */
    public function __construct(array $indicators)
    {
        if ($indicators === []) {
            throw new InvalidInputException('Delivery evidence requires at least one positive indicator.');
        }

        $normalized = [];

        foreach ($indicators as $name => $value) {
            if (!is_string($name) || Metric::tryFrom($name) === null) {
                throw new InvalidInputException('Delivery evidence indicators must use supported metric names.');
            }

            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value <= 0) {
                throw new InvalidInputException('Delivery evidence indicator values must be positive finite numbers.');
            }

            $normalized[$name] = (float) $value;
        }

        $this->indicators = $normalized;
    }

    public function has(Metric $metric): bool
    {
        return array_key_exists($metric->value, $this->indicators);
    }

    public function value(Metric $metric): ?float
    {
        return $this->indicators[$metric->value] ?? null;
    }

    /** @return non-empty-array<string, float> */
    public function indicators(): array
    {
        return $this->indicators;
    }
}
