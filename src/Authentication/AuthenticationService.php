<?php

declare(strict_types=1);

namespace MetaMetrics\Authentication;

use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\UnexpectedResponseException;

final readonly class AuthenticationService
{
    public function __construct(
        private MetaClientInterface $client,
        private MetaConfig $config,
        private MetaErrorMapper $errorMapper = new MetaErrorMapper(),
    ) {
    }

    public function validate(): AuthenticationResult
    {
        $response = $this->client->send(new Request(
            method: 'GET',
            path: sprintf('/%s/%s', $this->config->apiVersion(), $this->config->adAccountId()),
            query: ['fields' => 'id'],
        ));

        if (!$response->isSuccessful()) {
            throw $this->errorMapper->map($response);
        }

        $body = $response->body();

        if (!is_array($body) || ($body['id'] ?? null) !== $this->config->adAccountId()) {
            throw new UnexpectedResponseException(
                'Meta returned an unexpected response while validating the Ad Account.'
            );
        }

        return new AuthenticationResult($this->config->adAccountId());
    }
}
