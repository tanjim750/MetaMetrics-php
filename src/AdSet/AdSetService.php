<?php

declare(strict_types=1);

namespace MetaMetrics\AdSet;

use MetaMetrics\Authentication\MetaErrorMapper;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Pagination\Paginator;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Query\Fields;

final readonly class AdSetService
{
    private const DEFAULT_FIELDS = [
        'id',
        'name',
        'campaign_id',
        'status',
        'configured_status',
        'effective_status',
        'optimization_goal',
        'billing_event',
        'daily_budget',
        'lifetime_budget',
        'created_time',
        'updated_time',
        'start_time',
        'end_time',
    ];

    private const REQUIRED_FIELDS = ['id', 'name', 'campaign_id'];

    private MetaClientInterface $client;
    private MetaConfig $config;
    private AdSetNormalizer $normalizer;
    private Paginator $paginator;
    private MetaErrorMapper $errorMapper;

    public function __construct(
        MetaClientInterface $client,
        MetaConfig $config,
        ?AdSetNormalizer $normalizer = null,
        ?Paginator $paginator = null,
        ?MetaErrorMapper $errorMapper = null,
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->normalizer = $normalizer ?? new AdSetNormalizer();
        $this->errorMapper = $errorMapper ?? new MetaErrorMapper();
        $this->paginator = $paginator ?? new Paginator($client, $this->errorMapper);
    }

    /** @return list<AdSetDTO> */
    public function all(?AdSetQuery $query = null): array
    {
        return $this->collection($this->config->adAccountId(), $query);
    }

    /** @return list<AdSetDTO> */
    public function forCampaign(string $campaignId, ?AdSetQuery $query = null): array
    {
        if (trim($campaignId) === '') {
            throw new InvalidInputException('Campaign ID must not be empty.');
        }

        return $this->collection($campaignId, $query);
    }

    public function find(string $adSetId, ?Fields $fields = null): AdSetDTO
    {
        if (trim($adSetId) === '') {
            throw new InvalidInputException('Ad Set ID must not be empty.');
        }

        $response = $this->client->send(new Request(
            method: 'GET',
            path: sprintf(
                '/%s/%s',
                $this->config->apiVersion(),
                rawurlencode($adSetId),
            ),
            query: ['fields' => $this->adSetFields($fields)->toQueryValue()],
        ));

        if (!$response->isSuccessful()) {
            throw $this->errorMapper->map($response, 'Ad Set', $adSetId);
        }

        $body = $response->body();

        if (!is_array($body)) {
            throw new UnexpectedResponseException('Meta returned a malformed Ad Set response.');
        }

        return $this->normalizer->normalize($body);
    }

    /** @return list<AdSetDTO> */
    private function collection(string $parentId, ?AdSetQuery $query): array
    {
        $query ??= new AdSetQuery();
        $parameters = [
            'fields' => $this->adSetFields($query->fields())->toQueryValue(),
            ...$query->parameters(),
        ];
        $items = $this->paginator->fetchAll(new Request(
            method: 'GET',
            path: sprintf(
                '/%s/%s/adsets',
                $this->config->apiVersion(),
                rawurlencode($parentId),
            ),
            query: $parameters,
        ));

        return array_map($this->normalizer->normalize(...), $items);
    }

    private function adSetFields(?Fields $fields): Fields
    {
        $fields ??= new Fields(self::DEFAULT_FIELDS);

        foreach ($fields->values() as $field) {
            if (!in_array($field, self::DEFAULT_FIELDS, true)) {
                throw new InvalidInputException(sprintf('Unsupported normalized Ad Set field "%s".', $field));
            }
        }

        return $fields->withRequired(self::REQUIRED_FIELDS);
    }
}
