<?php

declare(strict_types=1);

namespace MetaMetrics\Insights;

use JsonException;
use MetaMetrics\Authentication\MetaErrorMapper;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Pagination\Paginator;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Insights\Metric\MetricRegistry;
use MetaMetrics\Insights\Normalizer\InsightsNormalizer;
use MetaMetrics\Insights\Parser\InsightsParser;
use MetaMetrics\Query\QueryBuilder;

final readonly class InsightsService
{
    private const RAW_CPR_FIELDS = ['spend', 'actions', 'cost_per_action_type'];
    private const AD_SET_CONFIGURATION_FIELDS = ['id', 'optimization_goal', 'promoted_object'];

    private MetaClientInterface $client;
    private MetaConfig $config;
    private MetricRegistry $registry;
    private InsightsParser $parser;
    private InsightsNormalizer $normalizer;
    private Paginator $paginator;
    private QueryBuilder $queryBuilder;
    private MetaErrorMapper $errorMapper;

    public function __construct(
        MetaClientInterface $client,
        MetaConfig $config,
        ?MetricRegistry $registry = null,
        ?InsightsParser $parser = null,
        ?InsightsNormalizer $normalizer = null,
        ?Paginator $paginator = null,
        ?QueryBuilder $queryBuilder = null,
        ?MetaErrorMapper $errorMapper = null,
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->registry = $registry ?? new MetricRegistry();
        $this->parser = $parser ?? new InsightsParser(registry: $this->registry);
        $this->normalizer = $normalizer ?? new InsightsNormalizer();
        $this->queryBuilder = $queryBuilder ?? new QueryBuilder();
        $this->errorMapper = $errorMapper ?? new MetaErrorMapper();
        $this->paginator = $paginator ?? new Paginator($client, $this->errorMapper);
    }

    /** @return list<InsightsDTO> */
    public function get(InsightsQuery $query): array
    {
        $foundationalMetrics = $this->registry->foundationalMetrics($query->metrics());
        $items = $this->paginator->fetchAll(new Request(
            method: 'GET',
            path: sprintf(
                '/%s/%s/insights',
                $this->config->apiVersion(),
                rawurlencode($query->entityId() ?? $this->config->adAccountId()),
            ),
            query: $this->parameters($query),
        ));
        $adSetConfigurations = $query->level() === InsightsLevel::AD_SET
            && $query->includesAdSetConfiguration()
            ? $this->adSetConfigurations($items)
            : [];

        $results = [];

        foreach ($items as $item) {
            $adSetId = $item['adset_id'] ?? null;
            $configuration = is_string($adSetId) ? ($adSetConfigurations[$adSetId] ?? null) : null;
            $parsed = $this->parser->parse(
                $item,
                $query->level(),
                $foundationalMetrics,
                $configuration,
            );
            $results[] = $this->normalizer->normalize($parsed, $query->level(), $query->metrics());
        }

        return $results;
    }

    /** @return array<string, scalar|null> */
    private function parameters(InsightsQuery $query): array
    {
        $parameters = [
            'fields' => implode(',', array_values(array_unique([
                ...$this->identityFields($query->level()),
                'date_start',
                'date_stop',
                ...self::RAW_CPR_FIELDS,
                ...$this->registry->metaFields($query->metrics()),
            ]))),
            'level' => $query->level()->value,
            'time_range' => $this->encodeTimeRange($query),
        ];

        if ($query->isDaily()) {
            $parameters['time_increment'] = 1;
        }

        $filtering = $this->queryBuilder->encodeFilters($query->filters(), InsightsFilterField::values());

        if ($filtering !== null) {
            $parameters['filtering'] = $filtering;
        }

        if ($query->limit() !== null) {
            $parameters['limit'] = $query->limit();
        }

        return $parameters;
    }

    /** @return list<string> */
    private function identityFields(InsightsLevel $level): array
    {
        return match ($level) {
            InsightsLevel::ACCOUNT => ['account_id', 'account_name'],
            InsightsLevel::CAMPAIGN => ['campaign_id', 'campaign_name'],
            InsightsLevel::AD_SET => ['campaign_id', 'adset_id', 'adset_name'],
            InsightsLevel::AD => ['campaign_id', 'adset_id', 'ad_id', 'ad_name'],
        };
    }

    private function encodeTimeRange(InsightsQuery $query): string
    {
        try {
            return json_encode($query->dateRange()->toArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidInputException('Insights date range could not be encoded.');
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    private function adSetConfigurations(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $id = $item['adset_id'] ?? null;

            if (!is_string($id) || $id === '') {
                throw new UnexpectedResponseException('Ad Set-level Insights require a valid "adset_id" field.');
            }

            $ids[$id] = $id;
        }

        $configurations = [];

        foreach ($ids as $id) {
            $response = $this->client->send(new Request(
                method: 'GET',
                path: sprintf(
                    '/%s/%s',
                    $this->config->apiVersion(),
                    rawurlencode($id),
                ),
                query: [
                    'fields' => implode(',', self::AD_SET_CONFIGURATION_FIELDS),
                ],
            ));

            if (!$response->isSuccessful()) {
                throw $this->errorMapper->map($response, 'Ad Set', $id);
            }

            $body = $response->body();

            if (!is_array($body)) {
                throw new UnexpectedResponseException('Meta returned malformed Ad Set configuration data.');
            }

            if (($body['id'] ?? null) !== $id) {
                throw new UnexpectedResponseException(sprintf(
                    'Meta did not return valid configuration for Ad Set "%s".',
                    $id,
                ));
            }

            $configurations[$id] = $body;
        }

        return $configurations;
    }
}
