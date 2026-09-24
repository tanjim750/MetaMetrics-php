<?php

declare(strict_types=1);

namespace MetaMetrics\Ad;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use MetaMetrics\Exception\UnexpectedResponseException;

final class AdNormalizer
{
    /** @param array<string, mixed> $ad */
    public function normalize(array $ad): AdDTO
    {
        return new AdDTO(
            id: $this->requiredString($ad, 'id'),
            name: $this->requiredString($ad, 'name'),
            adSetId: $this->requiredString($ad, 'adset_id'),
            campaignId: $this->requiredString($ad, 'campaign_id'),
            status: $this->optionalString($ad, 'status'),
            configuredStatus: $this->optionalString($ad, 'configured_status'),
            effectiveStatus: $this->optionalString($ad, 'effective_status'),
            createdTime: $this->optionalDateTime($ad, 'created_time'),
            updatedTime: $this->optionalDateTime($ad, 'updated_time'),
        );
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedResponseException(sprintf('Meta Ad response requires a valid "%s" field.', $field));
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
            throw new UnexpectedResponseException(sprintf('Meta Ad field "%s" must be a string.', $field));
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
            throw new UnexpectedResponseException(sprintf('Meta Ad field "%s" must be a valid timestamp.', $field));
        }
    }
}
