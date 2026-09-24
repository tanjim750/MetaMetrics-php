<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use LogicException;

final readonly class DeliveryAssessment
{
    private function __construct(
        private DeliveryStatus $status,
        private ?DeliveryEvidence $evidence = null,
    ) {
        if (($this->status === DeliveryStatus::DELIVERED) !== ($this->evidence !== null)) {
            throw new LogicException('Delivered assessments require evidence, and other assessments must not contain it.');
        }
    }

    public static function delivered(DeliveryEvidence $evidence): self
    {
        return new self(DeliveryStatus::DELIVERED, $evidence);
    }

    public static function notDelivered(): self
    {
        return new self(DeliveryStatus::NOT_DELIVERED);
    }

    public static function insufficientEvidence(): self
    {
        return new self(DeliveryStatus::INSUFFICIENT_EVIDENCE);
    }

    public function status(): DeliveryStatus
    {
        return $this->status;
    }

    public function evidence(): ?DeliveryEvidence
    {
        return $this->evidence;
    }
}
