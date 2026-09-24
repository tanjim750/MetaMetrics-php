<?php

declare(strict_types=1);

namespace MetaMetrics\AdSet;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use MetaMetrics\Exception\UnexpectedResponseException;

final class AdSetNormalizer
{
    /** @param array<string, mixed> $adSet */
    public function normalize(array $adSet): AdSetDTO
    {
        return new AdSetDTO(
            id: $this->requiredString($adSet, 'id'),
            name: $this->requiredString($adSet, 'name'),
            campaignId: $this->requiredString($adSet, 'campaign_id'),
            status: $this->optionalString($adSet, 'status'),
            configuredStatus: $this->optionalString($adSet, 'configured_status'),
            effectiveStatus: $this->optionalString($adSet, 'effective_status'),
            optimizationGoal: $this->optionalString($adSet, 'optimization_goal'),
            billingEvent: $this->optionalString($adSet, 'billing_event'),
            dailyBudget: $this->optionalString($adSet, 'daily_budget'),
            lifetimeBudget: $this->optionalString($adSet, 'lifetime_budget'),
            createdTime: $this->optionalDateTime($adSet, 'created_time'),
            updatedTime: $this->optionalDateTime($adSet, 'updated_time'),
            startTime: $this->optionalDateTime($adSet, 'start_time'),
            endTime: $this->optionalDateTime($adSet, 'end_time'),
        );
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedResponseException(sprintf('Meta Ad Set response requires a valid "%s" field.', $field));
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
            throw new UnexpectedResponseException(sprintf('Meta Ad Set field "%s" must be a string.', $field));
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
            throw new UnexpectedResponseException(sprintf('Meta Ad Set field "%s" must be a valid timestamp.', $field));
        }
    }
}
