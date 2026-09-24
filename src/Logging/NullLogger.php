<?php

declare(strict_types=1);

namespace MetaMetrics\Logging;

final class NullLogger implements LoggerInterface
{
    public function error(string $message, array $context = []): void
    {
    }
}
