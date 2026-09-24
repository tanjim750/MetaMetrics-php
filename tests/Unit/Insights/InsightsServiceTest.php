<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Insights;

use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\AuthenticationException;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\PermissionException;
use MetaMetrics\Exception\RateLimitException;
use MetaMetrics\Exception\ResourceNotFoundException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Exception\UnsupportedMetricException;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsFilterField;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\InsightsQuery;
use MetaMetrics\Insights\InsightsService;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Query\Filter;
use MetaMetrics\Query\FilterOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class InsightsServiceTest extends TestCase
{
    private const TOKEN = 'insights-secret-token';

    public function testItBuildsOnePaginatedDailyCampaignRequest(): void
    {
        $client = InsightsFakeMetaClient::withResponses(new Response(200, [
            'data' => [[
                'campaign_id' => 'campaign-1',
                'campaign_name' => 'September Sales',
                'date_start' => '2026-09-01',
                'date_stop' => '2026-09-01',
                'spend' => '100',
                'actions' => [['action_type' => 'link_click', 'value' => '20']],
                'cost_per_action_type' => [['action_type' => 'link_click', 'value' => '5']],
                'action_values' => [['action_type' => 'omni_purchase', 'value' => '300']],
                'clicks' => '20',
                'impressions' => '1000',
            ]],
        ]));
        $query = new InsightsQuery(
            level: InsightsLevel::CAMPAIGN,
            dateRange: new DateRange('2026-09-01', '2026-09-24'),
            metrics: [Metric::ROAS, Metric::CTR],
            daily: true,
            filters: [new Filter(
                InsightsFilterField::CAMPAIGN_ID->value,
                FilterOperator::IN,
                ['campaign-1'],
            )],
            limit: 50,
        );

        $results = $this->service($client)->get($query);

        self::assertCount(1, $results);
        self::assertSame(3.0, $results[0]->roas());
        self::assertSame(2.0, $results[0]->ctr());
        self::assertFalse($results[0]->hasMetric(Metric::SPEND));
        self::assertSame('100', $results[0]->rawData()->spend());
        self::assertSame(
            [['action_type' => 'link_click', 'value' => '20']],
            $results[0]->rawData()->actions(),
        );
        self::assertSame(
            [['action_type' => 'link_click', 'value' => '5']],
            $results[0]->rawData()->costPerActionType(),
        );

        $request = $client->requests()[0];
        self::assertSame('/v26.0/act_123456789/insights', $request->path());
        self::assertSame([
            'fields' => 'campaign_id,campaign_name,date_start,date_stop,spend,actions,cost_per_action_type,action_values,clicks,impressions',
            'level' => 'campaign',
            'time_range' => '{"since":"2026-09-01","until":"2026-09-24"}',
            'time_increment' => 1,
            'filtering' => '[{"field":"campaign.id","operator":"IN","value":["campaign-1"]}]',
            'limit' => 50,
        ], $request->query());
    }

    public function testItUsesAnExplicitEntityScopeWithoutChangingTheLevel(): void
    {
        $client = InsightsFakeMetaClient::withResponses(new Response(200, ['data' => []]));
        $query = new InsightsQuery(
            level: InsightsLevel::AD_SET,
            dateRange: new DateRange('2026-09-01', '2026-09-24'),
            metrics: [Metric::SPEND],
            entityId: 'campaign-1',
        );

        $this->service($client)->get($query);

        self::assertSame('/v26.0/campaign-1/insights', $client->requests()[0]->path());
        self::assertSame('adset', $client->requests()[0]->query()['level']);
        self::assertArrayNotHasKey('time_increment', $client->requests()[0]->query());
    }

    public function testAdSetInsightsIncludeConfigurationWithoutAssumingCustomEventType(): void
    {
        $promotedObject = [
            'custom_conversion_id' => 'conversion-123',
            'pixel_id' => 'pixel-456',
            'application_id' => 'application-789',
        ];
        $client = InsightsFakeMetaClient::withResponses(
            new Response(200, ['data' => [[
                'campaign_id' => 'campaign-1',
                'adset_id' => 'adset-1',
                'adset_name' => 'Conversions',
                'date_start' => '2026-09-01',
                'date_stop' => '2026-09-24',
                'spend' => '250.50',
                'actions' => [['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '5']],
                'cost_per_action_type' => [[
                    'action_type' => 'offsite_conversion.fb_pixel_purchase',
                    'value' => '50.10',
                ]],
            ]]]),
            new Response(200, [
                'id' => 'adset-1',
                'optimization_goal' => 'OFFSITE_CONVERSIONS',
                'promoted_object' => $promotedObject,
            ]),
        );
        $query = new InsightsQuery(
            level: InsightsLevel::AD_SET,
            dateRange: new DateRange('2026-09-01', '2026-09-24'),
            metrics: [Metric::SPEND],
        );

        $result = $this->service($client)->get($query)[0];

        self::assertSame('250.50', $result->rawData()->spend());
        self::assertSame('OFFSITE_CONVERSIONS', $result->rawData()->optimizationGoal());
        self::assertSame($promotedObject, $result->rawData()->promotedObject());
        self::assertArrayNotHasKey('custom_event_type', $result->rawData()->promotedObject() ?? []);
        self::assertSame([
            ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '50.10'],
        ], $result->rawData()->costPerActionType());

        self::assertCount(2, $client->requests());
        self::assertSame('/v26.0/adset-1', $client->requests()[1]->path());
        self::assertSame([
            'fields' => 'id,optimization_goal,promoted_object',
        ], $client->requests()[1]->query());
    }

    public function testEmptyInsightsAreSuccessful(): void
    {
        $client = InsightsFakeMetaClient::withResponses(new Response(200, ['data' => []]));

        $results = $this->service($client)->get($this->accountQuery());

        self::assertSame([], $results);
    }

    public function testItUsesSharedCursorPagination(): void
    {
        $client = InsightsFakeMetaClient::withResponses(
            new Response(200, [
                'data' => [$this->accountRow('2026-09-01', '10')],
                'paging' => [
                    'cursors' => ['after' => 'next-page'],
                    'next' => 'https://graph.facebook.com/next',
                ],
            ]),
            new Response(200, ['data' => [$this->accountRow('2026-09-02', '20')]]),
        );

        $results = $this->service($client)->get($this->accountQuery(daily: true));

        self::assertCount(2, $results);
        self::assertSame([10.0, 20.0], array_map(static fn ($result): ?float => $result->spend(), $results));
        self::assertSame('next-page', $client->requests()[1]->query()['after']);
    }

    #[DataProvider('apiErrorProvider')]
    public function testItMapsMetaErrors(int $status, int $code, string $expectedException): void
    {
        $client = InsightsFakeMetaClient::withResponses(new Response($status, [
            'error' => [
                'message' => 'Unsafe upstream message '.self::TOKEN,
                'type' => 'OAuthException',
                'code' => $code,
                'fbtrace_id' => 'trace-id',
            ],
        ]));

        try {
            $this->service($client)->get($this->accountQuery());
            self::fail('Expected a mapped Meta exception.');
        } catch (Throwable $exception) {
            self::assertInstanceOf($expectedException, $exception);
            self::assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            self::assertStringNotContainsString(self::TOKEN, serialize($exception));
        }
    }

    /** @return iterable<string, array{int, int, class-string<Throwable>}> */
    public static function apiErrorProvider(): iterable
    {
        yield 'authentication' => [401, 190, AuthenticationException::class];
        yield 'permission' => [403, 200, PermissionException::class];
        yield 'rate limit' => [429, 4, RateLimitException::class];
        yield 'resource not found' => [404, 100, ResourceNotFoundException::class];
    }

    public function testItRejectsMalformedResponses(): void
    {
        $client = InsightsFakeMetaClient::withResponses(new Response(200, ['data' => 'invalid']));

        $this->expectException(UnexpectedResponseException::class);

        $this->service($client)->get($this->accountQuery());
    }

    public function testInvalidQueriesFailBeforeANetworkRequest(): void
    {
        $client = InsightsFakeMetaClient::withResponses();

        try {
            new InsightsQuery(
                level: InsightsLevel::ACCOUNT,
                dateRange: new DateRange('2026-09-01', '2026-09-24'),
                metrics: [Metric::SPEND],
                entityId: ' ',
            );
            self::fail('Expected invalid input.');
        } catch (InvalidInputException) {
            self::assertSame([], $client->requests());
        }
    }

    public function testUnsupportedMetricsFailBeforeANetworkRequest(): void
    {
        $client = InsightsFakeMetaClient::withResponses();

        try {
            new InsightsQuery(
                level: InsightsLevel::ACCOUNT,
                dateRange: new DateRange('2026-09-01', '2026-09-24'),
                metrics: ['profit'],
            );
            self::fail('Expected an unsupported metric exception.');
        } catch (UnsupportedMetricException) {
            self::assertSame([], $client->requests());
        }
    }

    public function testUnsupportedFilterFieldsFailBeforeANetworkRequest(): void
    {
        $client = InsightsFakeMetaClient::withResponses();
        $query = new InsightsQuery(
            level: InsightsLevel::CAMPAIGN,
            dateRange: new DateRange('2026-09-01', '2026-09-24'),
            metrics: [Metric::SPEND],
            filters: [new Filter('account.currency', FilterOperator::EQUAL, 'BDT')],
        );

        try {
            $this->service($client)->get($query);
            self::fail('Expected unsupported filter input.');
        } catch (InvalidInputException) {
            self::assertSame([], $client->requests());
        }
    }

    private function service(InsightsFakeMetaClient $client): InsightsService
    {
        return new InsightsService($client, new MetaConfig(self::TOKEN, 'act_123456789', 'v26.0'));
    }

    private function accountQuery(bool $daily = false): InsightsQuery
    {
        return new InsightsQuery(
            level: InsightsLevel::ACCOUNT,
            dateRange: new DateRange('2026-09-01', '2026-09-24'),
            metrics: [Metric::SPEND],
            daily: $daily,
        );
    }

    /** @return array<string, string> */
    private function accountRow(string $date, string $spend): array
    {
        return [
            'account_id' => '123456789',
            'account_name' => 'Test Account',
            'date_start' => $date,
            'date_stop' => $date,
            'spend' => $spend,
        ];
    }
}

final class InsightsFakeMetaClient implements MetaClientInterface
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
