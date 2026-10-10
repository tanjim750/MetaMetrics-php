<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use JsonSerializable;
use MetaMetrics\Exception\InvalidInputException;

final readonly class HistoricalResponse implements JsonSerializable
{
    /** @param list<HistoricalResult> $data */
    public function __construct(
        private HistoricalLevel $level,
        private string $currency,
        private array $data,
    ) {
        if (preg_match('/\A[A-Z]{3}\z/D', $currency) !== 1) {
            throw new InvalidInputException('Historical response requires a valid currency code.');
        }

        foreach ($data as $row) {
            if (!$row instanceof HistoricalResult || $row->level() !== $level || $row->currency() !== $currency) {
                throw new InvalidInputException('Historical response rows must share its level and currency.');
            }
        }
    }

    public function level(): HistoricalLevel { return $this->level; }
    public function currency(): string { return $this->currency; }

    /** @return list<HistoricalResult> */
    public function data(): array { return $this->data; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'currency' => $this->currency,
            'data' => array_map(static fn (HistoricalResult $row): array => $row->toArray(false), $this->data),
        ];
    }

    public function jsonSerialize(): array { return $this->toArray(); }
}
