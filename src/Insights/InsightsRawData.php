<?php

declare(strict_types=1);

namespace MetaMetrics\Insights;

final readonly class InsightsRawData
{
    /**
     * @param list<array<string, mixed>>|null $actions
     * @param list<array<string, mixed>>|null $costPerActionType
     * @param array<string, mixed>|null $promotedObject
     */
    public function __construct(
        private string|int|float|null $spend,
        private ?array $actions,
        private ?array $costPerActionType,
        private ?string $optimizationGoal,
        private ?array $promotedObject,
    ) {
    }

    public function spend(): string|int|float|null
    {
        return $this->spend;
    }

    /** @return list<array<string, mixed>>|null */
    public function actions(): ?array
    {
        return $this->actions;
    }

    /** @return list<array<string, mixed>>|null */
    public function costPerActionType(): ?array
    {
        return $this->costPerActionType;
    }

    public function optimizationGoal(): ?string
    {
        return $this->optimizationGoal;
    }

    /** @return array<string, mixed>|null */
    public function promotedObject(): ?array
    {
        return $this->promotedObject;
    }
}
