<?php

declare(strict_types=1);

namespace MetaMetrics\Insights;

use JsonSerializable;
use MetaMetrics\Exception\InvalidInputException;

final readonly class InsightsResponse implements JsonSerializable
{
    /** @param list<InsightsDTO> $data */
    public function __construct(
        private InsightsLevel $level,
        private string $currency,
        private array $data,
    ) {
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new InvalidInputException('Insights response requires a valid currency code.');
        }

        foreach ($data as $row) {
            if (!$row instanceof InsightsDTO || $row->level() !== $level || $row->currency() !== $currency) {
                throw new InvalidInputException('Insights response rows must share its level and currency.');
            }
        }
    }

    public function level(): InsightsLevel { return $this->level; }
    public function currency(): string { return $this->currency; }

    /** @return list<InsightsDTO> */
    public function data(): array { return $this->data; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'currency' => $this->currency,
            'data' => array_map(static fn (InsightsDTO $row): array => $row->toArray(false), $this->data),
        ];
    }

    public function jsonSerialize(): array { return $this->toArray(); }
}
