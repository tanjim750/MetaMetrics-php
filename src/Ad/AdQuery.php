<?php

declare(strict_types=1);

namespace MetaMetrics\Ad;

use JsonException;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Query\Fields;

final readonly class AdQuery
{
    private const EFFECTIVE_STATUSES = [
        'ACTIVE',
        'ADSET_PAUSED',
        'ARCHIVED',
        'CAMPAIGN_PAUSED',
        'DELETED',
        'DISAPPROVED',
        'IN_PROCESS',
        'PAUSED',
        'PENDING_BILLING_INFO',
        'PENDING_REVIEW',
        'PREAPPROVED',
        'WITH_ISSUES',
    ];

    /** @param list<string> $effectiveStatuses */
    public function __construct(
        private ?Fields $fields = null,
        private array $effectiveStatuses = [],
        private ?int $limit = null,
    ) {
        foreach ($this->effectiveStatuses as $status) {
            if (!in_array($status, self::EFFECTIVE_STATUSES, true)) {
                throw new InvalidInputException(sprintf('Unsupported Ad effective status "%s".', $status));
            }
        }

        if ($this->limit !== null && $this->limit < 1) {
            throw new InvalidInputException('Ad query limit must be greater than zero.');
        }
    }

    public function fields(): ?Fields
    {
        return $this->fields;
    }

    /** @return array<string, scalar|null> */
    public function parameters(): array
    {
        $parameters = [];

        if ($this->effectiveStatuses !== []) {
            try {
                $parameters['effective_status'] = json_encode(
                    array_values(array_unique($this->effectiveStatuses)),
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                throw new InvalidInputException('Ad effective statuses could not be encoded.');
            }
        }

        if ($this->limit !== null) {
            $parameters['limit'] = $this->limit;
        }

        return $parameters;
    }
}
