<?php

declare(strict_types=1);

namespace MetaMetrics\Config;

final readonly class MetaConfig
{
    private string $accessToken;

    private string $adAccountId;

    private string $apiVersion;

    public function __construct(
        string $accessToken,
        string $adAccountId,
        string $apiVersion,
    ) {
        ConfigurationValidator::validateAccessToken($accessToken);
        ConfigurationValidator::validateApiVersion($apiVersion);

        $this->accessToken = $accessToken;
        $this->adAccountId = ConfigurationValidator::normalizeAdAccountId($adAccountId);
        $this->apiVersion = $apiVersion;
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function adAccountId(): string
    {
        return $this->adAccountId;
    }

    public function apiVersion(): string
    {
        return $this->apiVersion;
    }

    /**
     * @return array{accessToken: string, adAccountId: string, apiVersion: string}
     */
    public function __debugInfo(): array
    {
        return [
            'accessToken' => '[REDACTED]',
            'adAccountId' => $this->adAccountId,
            'apiVersion' => $this->apiVersion,
        ];
    }

    /**
     * @return array{accessToken: string, adAccountId: string, apiVersion: string}
     */
    public function __serialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * Redacted serialized configuration cannot be restored as valid credentials.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('MetaConfig cannot be unserialized.');
    }
}
