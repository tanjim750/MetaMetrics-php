<?php

declare(strict_types=1);

namespace MetaMetrics\Ad;

use MetaMetrics\Authentication\MetaErrorMapper;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Pagination\Paginator;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Query\Fields;

final readonly class AdService
{
    private const DEFAULT_FIELDS = [
        'id',
        'name',
        'adset_id',
        'campaign_id',
        'status',
        'configured_status',
        'effective_status',
        'created_time',
        'updated_time',
    ];

    private const REQUIRED_FIELDS = ['id', 'name', 'adset_id', 'campaign_id'];

    private MetaClientInterface $client;
    private MetaConfig $config;
    private AdNormalizer $normalizer;
    private Paginator $paginator;
    private MetaErrorMapper $errorMapper;

    public function __construct(
        MetaClientInterface $client,
        MetaConfig $config,
        ?AdNormalizer $normalizer = null,
        ?Paginator $paginator = null,
        ?MetaErrorMapper $errorMapper = null,
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->normalizer = $normalizer ?? new AdNormalizer();
        $this->errorMapper = $errorMapper ?? new MetaErrorMapper();
        $this->paginator = $paginator ?? new Paginator($client, $this->errorMapper);
    }

    /** @return list<AdDTO> */
    public function all(?AdQuery $query = null): array
    {
        return $this->collection($this->config->adAccountId(), $query);
    }

    /** @return list<AdDTO> */
    public function forCampaign(string $campaignId, ?AdQuery $query = null): array
    {
        return $this->collection($this->requiredId($campaignId, 'Campaign'), $query);
    }

    /** @return list<AdDTO> */
    public function forAdSet(string $adSetId, ?AdQuery $query = null): array
    {
        return $this->collection($this->requiredId($adSetId, 'Ad Set'), $query);
    }

    public function find(string $adId, ?Fields $fields = null): AdDTO
    {
        $adId = $this->requiredId($adId, 'Ad');
        $response = $this->client->send(new Request(
            method: 'GET',
            path: sprintf(
                '/%s/%s',
                $this->config->apiVersion(),
                rawurlencode($adId),
            ),
            query: ['fields' => $this->adFields($fields)->toQueryValue()],
        ));

        if (!$response->isSuccessful()) {
            throw $this->errorMapper->map($response, 'Ad', $adId);
        }

        $body = $response->body();

        if (!is_array($body)) {
            throw new UnexpectedResponseException('Meta returned a malformed Ad response.');
        }

        return $this->normalizer->normalize($body);
    }

    /** @return list<AdDTO> */
    private function collection(string $parentId, ?AdQuery $query): array
    {
        $query ??= new AdQuery();
        $parameters = [
            'fields' => $this->adFields($query->fields())->toQueryValue(),
            ...$query->parameters(),
        ];
        $items = $this->paginator->fetchAll(new Request(
            method: 'GET',
            path: sprintf(
                '/%s/%s/ads',
                $this->config->apiVersion(),
                rawurlencode($parentId),
            ),
            query: $parameters,
        ));

        return array_map($this->normalizer->normalize(...), $items);
    }

    private function adFields(?Fields $fields): Fields
    {
        $fields ??= new Fields(self::DEFAULT_FIELDS);

        foreach ($fields->values() as $field) {
            if (!in_array($field, self::DEFAULT_FIELDS, true)) {
                throw new InvalidInputException(sprintf('Unsupported normalized Ad field "%s".', $field));
            }
        }

        return $fields->withRequired(self::REQUIRED_FIELDS);
    }

    private function requiredId(string $id, string $label): string
    {
        if (trim($id) === '') {
            throw new InvalidInputException(sprintf('%s ID must not be empty.', $label));
        }

        return $id;
    }
}
