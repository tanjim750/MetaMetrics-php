<?php

declare(strict_types=1);

namespace MetaMetrics\Tracking;

final readonly class TrackingIdentifiers
{
    public function __construct(
        private ?string $campaignId = null,
        private ?string $adSetId = null,
        private ?string $adId = null,
        private ?string $source = null,
        private ?string $medium = null,
    ) {
    }

    public function campaignId(): ?string
    {
        return $this->campaignId;
    }

    public function adSetId(): ?string
    {
        return $this->adSetId;
    }

    public function adId(): ?string
    {
        return $this->adId;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function medium(): ?string
    {
        return $this->medium;
    }
}
