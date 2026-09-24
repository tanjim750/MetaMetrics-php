<?php

declare(strict_types=1);

namespace MetaMetrics\Campaign;

use MetaMetrics\Authentication\MetaErrorMapper;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Pagination\Paginator;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Query\Fields;

final readonly class CampaignService
{
    private const DEFAULT_FIELDS = [
        'id',
        'name',
        'status',
        'configured_status',
        'effective_status',
        'objective',
        'created_time',
        'updated_time',
    ];

    private const REQUIRED_FIELDS = ['id', 'name'];

    private MetaClientInterface $client;
    private MetaConfig $config;
    private CampaignNormalizer $normalizer;
    private Paginator $paginator;
    private MetaErrorMapper $errorMapper;

    public function __construct(
        MetaClientInterface $client,
        MetaConfig $config,
        ?CampaignNormalizer $normalizer = null,
        ?Paginator $paginator = null,
        ?MetaErrorMapper $errorMapper = null,
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->normalizer = $normalizer ?? new CampaignNormalizer();
        $this->errorMapper = $errorMapper ?? new MetaErrorMapper();
        $this->paginator = $paginator ?? new Paginator($client, $this->errorMapper);
    }

    /** @return list<CampaignDTO> */
    public function all(?CampaignQuery $query = null): array
    {
        $query ??= new CampaignQuery();
        $fields = $this->campaignFields($query->fields());
        $parameters = [
            'fields' => $fields->toQueryValue(),
            ...$query->parameters(),
        ];

        $items = $this->paginator->fetchAll(new Request(
            method: 'GET',
            path: sprintf(
                '/%s/%s/campaigns',
                $this->config->apiVersion(),
                $this->config->adAccountId(),
            ),
            query: $parameters,
        ));

        return array_map($this->normalizer->normalize(...), $items);
    }

    public function find(string $campaignId, ?Fields $fields = null): CampaignDTO
    {
        if (trim($campaignId) === '') {
            throw new InvalidInputException('Campaign ID must not be empty.');
        }

        $response = $this->client->send(new Request(
            method: 'GET',
            path: sprintf(
                '/%s/%s',
                $this->config->apiVersion(),
                rawurlencode($campaignId),
            ),
            query: ['fields' => $this->campaignFields($fields)->toQueryValue()],
        ));

        if (!$response->isSuccessful()) {
            throw $this->errorMapper->map($response, 'Campaign', $campaignId);
        }

        $body = $response->body();

        if (!is_array($body)) {
            throw new UnexpectedResponseException('Meta returned a malformed Campaign response.');
        }

        return $this->normalizer->normalize($body);
    }

    private function campaignFields(?Fields $fields): Fields
    {
        $fields ??= new Fields(self::DEFAULT_FIELDS);

        foreach ($fields->values() as $field) {
            if (!in_array($field, self::DEFAULT_FIELDS, true)) {
                throw new InvalidInputException(sprintf('Unsupported normalized Campaign field "%s".', $field));
            }
        }

        return $fields->withRequired(self::REQUIRED_FIELDS);
    }
}
