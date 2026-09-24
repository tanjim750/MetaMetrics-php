<?php

declare(strict_types=1);

namespace MetaMetrics\Account;

use MetaMetrics\Exception\UnexpectedResponseException;

final class AccountNormalizer
{
    /** @param array<string, mixed> $account */
    public function normalize(array $account): AccountDTO
    {
        return new AccountDTO(
            id: $this->requiredString($account, 'id'),
            name: $this->requiredString($account, 'name'),
            accountId: $this->requiredString($account, 'account_id'),
            accountStatus: $this->optionalInteger($account, 'account_status'),
            currency: $this->optionalString($account, 'currency'),
            timezoneName: $this->optionalString($account, 'timezone_name'),
        );
    }

    /** @param array<string, mixed> $account */
    private function requiredString(array $account, string $field): string
    {
        $value = $account[$field] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedResponseException(sprintf(
                'Meta Ad Account response requires a valid "%s" field.',
                $field,
            ));
        }

        return $value;
    }

    /** @param array<string, mixed> $account */
    private function optionalString(array $account, string $field): ?string
    {
        if (!array_key_exists($field, $account) || $account[$field] === null) {
            return null;
        }

        if (!is_string($account[$field]) || $account[$field] === '') {
            throw new UnexpectedResponseException(sprintf(
                'Meta Ad Account field "%s" must be a non-empty string.',
                $field,
            ));
        }

        return $account[$field];
    }

    /** @param array<string, mixed> $account */
    private function optionalInteger(array $account, string $field): ?int
    {
        if (!array_key_exists($field, $account) || $account[$field] === null) {
            return null;
        }

        if (!is_int($account[$field])) {
            throw new UnexpectedResponseException(sprintf(
                'Meta Ad Account field "%s" must be an integer.',
                $field,
            ));
        }

        return $account[$field];
    }
}
