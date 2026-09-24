<?php

declare(strict_types=1);

namespace MetaMetrics\Authentication;

final readonly class MetaError
{
    public function __construct(
        private int $httpStatus,
        private ?string $message,
        private ?string $type,
        private ?int $code,
        private ?int $subcode,
        private ?string $traceId,
        private ?bool $transient,
    ) {
    }

    public function httpStatus(): int { return $this->httpStatus; }
    public function message(): ?string { return $this->message; }
    public function type(): ?string { return $this->type; }
    public function code(): ?int { return $this->code; }
    public function subcode(): ?int { return $this->subcode; }
    public function traceId(): ?string { return $this->traceId; }
    public function isTransient(): ?bool { return $this->transient; }
}
