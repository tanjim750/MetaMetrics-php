<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Historical;

use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\AuthenticationException;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\NetworkException;
use MetaMetrics\Exception\PermissionException;
use MetaMetrics\Exception\RateLimitException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Historical\HistoricalQuery;
use MetaMetrics\Historical\HistoricalService;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsFilterField;
use MetaMetrics\Insights\InsightsService;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Query\Filter;
use MetaMetrics\Query\FilterOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class HistoricalServiceTest extends TestCase
{
    public function testAggregateDiscoveryUsesInsightsEvidenceAndRequestedMetrics(): void
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = require dirname(__DIR__, 2).'/Fixtures/MetaResponses/historical_campaigns.php';
        $client = HistoricalFakeMetaClient::withResponses(new Response(200, ['data' => $rows]));
        $query = new HistoricalQuery(
            level: HistoricalLevel::CAMPAIGN,
            dateRange: $this->range(),
            metrics: [Metric::ROAS],
            filters: [new Filter(
                InsightsFilterField::CAMPAIGN_ID->value,
                FilterOperator::IN,
                ['campaign-before-range', 'campaign-created-in-range'],
            )],
            limit: 100,
        );

        $results = $this->service($client)->get($query);

        self::assertSame(
            ['campaign-before-range', 'campaign-created-in-range'],
            array_map(static fn ($result): string => $result->entityId(), $results),
        );
        self::assertSame(2.0, $results[0]->insights()->roas());
        self::assertSame('2026-09-01', $results[0]->requestedSince());
        self::assertSame('2026-09-24', $results[0]->requestedUntil());
        self::assertSame(['impressions' => 10000.0, 'spend' => 500.0, 'reach' => 8000.0], $results[0]->deliveryEvidence()->indicators());

        self::assertCount(1, $client->requests());
        self::assertSame('/v26.0/act_123/insights', $client->requests()[0]->path());
        self::assertSame('campaign', $client->requests()[0]->query()['level']);
        self::assertSame(
            'campaign_id,campaign_name,date_start,date_stop,spend,actions,cost_per_action_type,impressions,reach,action_values',
            $client->requests()[0]->query()['fields'],
        );
        self::assertArrayNotHasKey('time_increment', $client->requests()[0]->query());
        self::assertSame(100, $client->requests()[0]->query()['limit']);
        self::assertStringNotContainsString('status', $client->requests()[0]->query()['fields']);
        self::assertStringNotContainsString('created_time', $client->requests()[0]->query()['fields']);
    }

    public function testEmptyInsightsAreAValidEmptyHistoricalResult(): void
    {
        $client = HistoricalFakeMetaClient::withResponses(new Response(200, ['data' => []]));

        self::assertSame([], $this->service($client)->campaigns($this->range()));
    }

    public function testDailyDiscoveryDoesNotFillGapsOrInferContinuousDelivery(): void
    {
        $client = HistoricalFakeMetaClient::withResponses(new Response(200, ['data' => [
            $this->campaignRow('2026-09-01', '10', '100'),
            $this->campaignRow('2026-09-03', '0', '0'),
        ]]));
        $query = new HistoricalQuery(
            HistoricalLevel::CAMPAIGN,
            new DateRange('2026-09-01', '2026-09-03'),
            daily: true,
        );

        $results = $this->service($client)->get($query);

        self::assertCount(1, $results);
        self::assertSame('2026-09-01', $results[0]->insights()->dateStart());
        self::assertSame('2026-09-03', $results[0]->requestedUntil());
        self::assertSame(1, $client->requests()[0]->query()['time_increment']);
    }

    public function testAdSetDiscoveryPreservesCampaignRelationshipAndUsesParentScope(): void
    {
        $client = HistoricalFakeMetaClient::withResponses(new Response(200, ['data' => [[
                'campaign_id' => 'campaign-1',
                'adset_id' => 'adset-1',
                'adset_name' => 'Ad Set',
                'date_start' => '2026-09-01',
                'date_stop' => '2026-09-24',
                'spend' => '5',
                'impressions' => '50',
                'reach' => '40',
            ]]]));

        $results = $this->service($client)->adSets($this->range(), 'campaign-1');

        self::assertCount(1, $results);
        self::assertSame('campaign-1', $results[0]->campaignId());
        self::assertSame('adset-1', $results[0]->adSetId());
        self::assertSame('/v26.0/campaign-1/insights', $client->requests()[0]->path());
        self::assertCount(1, $client->requests());
        self::assertNull($results[0]->insights()->rawData()->optimizationGoal());
    }

    public function testAdDiscoveryPreservesAllRelationships(): void
    {
        $client = HistoricalFakeMetaClient::withResponses(new Response(200, ['data' => [[
            'campaign_id' => 'campaign-1',
            'adset_id' => 'adset-1',
            'ad_id' => 'ad-1',
            'ad_name' => 'Ad',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '2',
            'impressions' => '20',
            'reach' => '15',
        ]]]));

        $results = $this->service($client)->ads($this->range(), 'adset-1');

        self::assertSame('campaign-1', $results[0]->campaignId());
        self::assertSame('adset-1', $results[0]->adSetId());
        self::assertSame('ad-1', $results[0]->adId());
        self::assertSame('/v26.0/adset-1/insights', $client->requests()[0]->path());
    }

    public function testAllPaginatedRowsAreEvaluated(): void
    {
        $client = HistoricalFakeMetaClient::withResponses(
            new Response(200, [
                'data' => [$this->campaignRow('2026-09-01', '1', '10', 'campaign-1')],
                'paging' => [
                    'cursors' => ['after' => 'page-2'],
                    'next' => 'https://graph.facebook.com/next',
                ],
            ]),
            new Response(200, ['data' => [
                $this->campaignRow('2026-09-01', '2', '20', 'campaign-2'),
            ]]),
        );

        $results = $this->service($client)->campaigns($this->range());

        self::assertSame(['campaign-1', 'campaign-2'], array_map(
            static fn ($result): string => $result->entityId(),
            $results,
        ));
        self::assertSame('page-2', $client->requests()[1]->query()['after']);
    }

    #[DataProvider('apiFailureProvider')]
    public function testApiFailuresAreNotReinterpretedAsEmptyHistory(
        int $status,
        int $code,
        string $expectedException,
    ): void
    {
        $token = 'historical-secret';
        $client = HistoricalFakeMetaClient::withResponses(new Response($status, [
            'error' => ['message' => 'Unsafe upstream message '.$token, 'code' => $code],
        ]));

        try {
            $this->service($client)->campaigns($this->range());
            self::fail('Expected a mapped Meta exception.');
        } catch (Throwable $exception) {
            self::assertInstanceOf($expectedException, $exception);
            self::assertStringNotContainsString($token, $exception->getMessage());
            self::assertStringNotContainsString($token, serialize($exception));
        }
    }

    /** @return iterable<string, array{int, int, class-string<Throwable>}> */
    public static function apiFailureProvider(): iterable
    {
        yield 'authentication' => [401, 190, AuthenticationException::class];
        yield 'permission' => [403, 200, PermissionException::class];
        yield 'rate limit' => [429, 4, RateLimitException::class];
    }

    public function testMidPaginationFailureDoesNotReturnPartialResults(): void
    {
        $client = HistoricalFakeMetaClient::withResponses(
            new Response(200, [
                'data' => [$this->campaignRow('2026-09-01', '1', '10')],
                'paging' => [
                    'cursors' => ['after' => 'page-2'],
                    'next' => 'https://graph.facebook.com/next',
                ],
            ]),
            new NetworkException('Request failed.'),
        );

        $this->expectException(NetworkException::class);

        $this->service($client)->campaigns($this->range());
    }

    public function testInvalidParentScopeFailsBeforeAnyRequest(): void
    {
        $client = HistoricalFakeMetaClient::withResponses();

        try {
            $this->service($client)->get(new HistoricalQuery(
                HistoricalLevel::AD_SET,
                $this->range(),
                parentId: ' ',
            ));
            self::fail('Expected invalid input.');
        } catch (InvalidInputException) {
            self::assertSame([], $client->requests());
        }
    }

    public function testInvalidDateRangeFailsBeforeAnyRequest(): void
    {
        $client = HistoricalFakeMetaClient::withResponses();

        try {
            $this->service($client)->campaigns(new DateRange('2026-09-24', '2026-09-01'));
            self::fail('Expected invalid input.');
        } catch (InvalidInputException) {
            self::assertSame([], $client->requests());
        }
    }

    public function testMissingDeliveryMetricsAreReportedAsInsufficientEvidence(): void
    {
        $client = HistoricalFakeMetaClient::withResponses(new Response(200, ['data' => [[
            'campaign_id' => 'campaign-1',
            'campaign_name' => 'Campaign',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
        ]]]));

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('insufficient delivery evidence');

        $this->service($client)->campaigns($this->range());
    }

    private function service(HistoricalFakeMetaClient $client): HistoricalService
    {
        $config = new MetaConfig('historical-secret', 'act_123', 'v26.0');

        return new HistoricalService(new InsightsService($client, $config));
    }

    private function range(): DateRange
    {
        return new DateRange('2026-09-01', '2026-09-24');
    }

    /** @return array<string, string> */
    private function campaignRow(
        string $date,
        string $spend,
        string $impressions,
        string $id = 'campaign-1',
    ): array {
        return [
            'campaign_id' => $id,
            'campaign_name' => 'Campaign',
            'date_start' => $date,
            'date_stop' => $date,
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => $impressions,
        ];
    }
}

final class HistoricalFakeMetaClient implements MetaClientInterface
{
    /** @var list<Response|Throwable> */
    private array $responses;

    /** @var list<Request> */
    private array $requests = [];

    private function __construct(Response|Throwable ...$responses)
    {
        $this->responses = $responses;
    }

    public static function withResponses(Response|Throwable ...$responses): self
    {
        return new self(...$responses);
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        if (!$response instanceof Response) {
            throw new \LogicException('The fake Meta client has no response queued.');
        }

        return $response;
    }

    /** @return list<Request> */
    public function requests(): array
    {
        return $this->requests;
    }
}
