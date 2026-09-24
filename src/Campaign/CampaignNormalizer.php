<?php

declare(strict_types=1);

namespace MetaMetrics\Campaign;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use MetaMetrics\Exception\UnexpectedResponseException;

final class CampaignNormalizer
{
    /** @param array<string, mixed> $campaign */
    public function normalize(array $campaign): CampaignDTO
    {
        return new CampaignDTO(
            id: $this->requiredString($campaign, 'id'),
            name: $this->requiredString($campaign, 'name'),
            status: $this->optionalString($campaign, 'status'),
            configuredStatus: $this->optionalString($campaign, 'configured_status'),
            effectiveStatus: $this->optionalString($campaign, 'effective_status'),
            objective: $this->optionalString($campaign, 'objective'),
            createdTime: $this->optionalDateTime($campaign, 'created_time'),
            updatedTime: $this->optionalDateTime($campaign, 'updated_time'),
        );
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedResponseException(sprintf('Meta Campaign response requires a valid "%s" field.', $field));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function optionalString(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_string($data[$field]) || $data[$field] === '') {
            throw new UnexpectedResponseException(sprintf('Meta Campaign field "%s" must be a string.', $field));
        }

        return $data[$field];
    }

    /** @param array<string, mixed> $data */
    private function optionalDateTime(array $data, string $field): ?DateTimeImmutable
    {
        $value = $this->optionalString($data, $field);

        if ($value === null) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception) {
            throw new UnexpectedResponseException(sprintf('Meta Campaign field "%s" must be a valid timestamp.', $field));
        }
    }
}
