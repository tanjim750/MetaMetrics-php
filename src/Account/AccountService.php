<?php

declare(strict_types=1);

namespace MetaMetrics\Account;

use MetaMetrics\Authentication\MetaErrorMapper;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\UnexpectedResponseException;

final readonly class AccountService
{
    private const FIELDS = [
        'id',
        'name',
        'account_id',
        'account_status',
        'currency',
        'timezone_name',
    ];

    public function __construct(
        private MetaClientInterface $client,
        private MetaConfig $config,
        private AccountNormalizer $normalizer = new AccountNormalizer(),
        private MetaErrorMapper $errorMapper = new MetaErrorMapper(),
    ) {
    }

    public function get(): AccountDTO
    {
        $response = $this->client->send(new Request(
            method: 'GET',
            path: sprintf('/%s/%s', $this->config->apiVersion(), $this->config->adAccountId()),
            query: ['fields' => implode(',', self::FIELDS)],
        ));

        if (!$response->isSuccessful()) {
            throw $this->errorMapper->map(
                $response,
                'Ad Account',
                $this->config->adAccountId(),
            );
        }

        $body = $response->body();

        if (!is_array($body)) {
            throw new UnexpectedResponseException(
                'Meta returned a malformed Ad Account response.',
                httpStatus: $response->statusCode(),
            );
        }

        $account = $this->normalizer->normalize($body);
        $expectedId = $this->config->adAccountId();
        $expectedAccountId = substr($expectedId, 4);

        if ($account->id() !== $expectedId || $account->accountId() !== $expectedAccountId) {
            throw new UnexpectedResponseException(
                'Meta returned data for an unexpected Ad Account.',
                httpStatus: $response->statusCode(),
                resourceType: 'Ad Account',
                resourceId: $expectedId,
            );
        }

        return $account;
    }
}
