<?php

declare(strict_types=1);

namespace MetaMetrics\Config;

use MetaMetrics\Exception\InvalidConfigurationException;

final class ConfigurationValidator
{
    public static function validateAccessToken(string $accessToken): void
    {
        if (trim($accessToken) === '') {
            throw new InvalidConfigurationException('Meta Access Token must not be empty.');
        }
    }

    public static function normalizeAdAccountId(string $adAccountId): string
    {
        if (preg_match('/\A[0-9]+\z/D', $adAccountId) === 1) {
            $adAccountId = 'act_'.$adAccountId;
        }

        if (preg_match('/\Aact_[0-9]+\z/D', $adAccountId) !== 1) {
            throw new InvalidConfigurationException(
                'Meta Ad Account ID must follow the format "act_<numeric-id>".'
            );
        }

        return $adAccountId;
    }

    public static function validateApiVersion(string $apiVersion): void
    {
        if (preg_match('/\Av[0-9]+\.[0-9]+\z/D', $apiVersion) !== 1) {
            throw new InvalidConfigurationException(
                'Meta Marketing API Version must follow the format "vXX.X".'
            );
        }
    }
}
