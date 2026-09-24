<?php

declare(strict_types=1);

namespace MetaMetrics\Logging;

interface LoggerInterface
{
    /** @param array<string, bool|float|int|string|null> $context */
    public function error(string $message, array $context = []): void;
}
