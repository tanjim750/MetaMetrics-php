<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Campaign;

use MetaMetrics\Campaign\CampaignQuery;
use MetaMetrics\Campaign\CampaignService;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\AuthenticationException;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\NetworkException;
use MetaMetrics\Exception\PermissionException;
use MetaMetrics\Exception\RateLimitException;
use MetaMetrics\Exception\ResourceNotFoundException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Query\Fields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class CampaignServiceTest extends TestCase
{
    private const TOKEN = 'campaign-secret-token';

    public function testItRetrievesAndNormalizesCampaigns(): void
    {
        $client = CampaignFakeMetaClient::withResponses(new Response(200, [
            'data' => [
                $this->campaign('1', 'Campaign One', 'ACTIVE', 'ACTIVE'),
                $this->campaign('2', 'Campaign Two', 'PAUSED', 'PAUSED'),
            ],
        ]));

        $campaigns = $this->service($client)->all();

        self::assertCount(2, $campaigns);
        self::assertSame('1', $campaigns[0]->id());
        self::assertSame('Campaign One', $campaigns[0]->name());
        self::assertSame('ACTIVE', $campaigns[0]->status());
        self::assertSame('ACTIVE', $campaigns[0]->effectiveStatus());
        self::assertSame('2', $campaigns[1]->id());
        self::assertSame('PAUSED', $campaigns[1]->status());

        $request = $client->requests()[0];
        self::assertSame('GET', $request->method());
        self::assertSame('/v26.0/act_123456789/campaigns', $request->path());
        self::assertSame(
            'id,name,status,configured_status,effective_status,objective,created_time,updated_time',
            $request->query()['fields'],
        );
    }

    public function testCurrentStatusIsPreservedWithoutHistoricalInterpretation(): void
    {
        $client = CampaignFakeMetaClient::withResponses(new Response(200, [
            'data' => [$this->campaign('1', 'Historical Campaign', 'PAUSED', 'PAUSED')],
        ]));

        $campaign = $this->service($client)->all()[0];

        self::assertSame('PAUSED', $campaign->status());
        self::assertSame('PAUSED', $campaign->configuredStatus());
        self::assertSame('PAUSED', $campaign->effectiveStatus());
    }

    public function testItUsesSupportedQueryOptionsAndSelectedFields(): void
    {
        $client = CampaignFakeMetaClient::withResponses(new Response(200, [
            'data' => [['id' => '1', 'name' => 'Campaign', 'objective' => 'OUTCOME_TRAFFIC']],
        ]));
        $query = new CampaignQuery(
            fields: new Fields(['objective']),
            effectiveStatuses: ['ACTIVE', 'PAUSED', 'ACTIVE'],
            completed: false,
            limit: 25,
        );

        $this->service($client)->all($query);

        self::assertSame([
            'fields' => 'id,name,objective',
            'effective_status' => '["ACTIVE","PAUSED"]',
            'is_completed' => false,
            'limit' => 25,
        ], $client->requests()[0]->query());
    }

    public function testItFollowsMetaCursorPagination(): void
    {
        $client = CampaignFakeMetaClient::withResponses(
            new Response(200, [
                'data' => [$this->campaign('1', 'First', 'ACTIVE', 'ACTIVE')],
                'paging' => [
                    'cursors' => ['after' => 'next-cursor'],
                    'next' => 'https://graph.facebook.com/next-page',
                ],
            ]),
            new Response(200, [
                'data' => [$this->campaign('2', 'Second', 'PAUSED', 'PAUSED')],
            ]),
        );

        $campaigns = $this->service($client)->all(new CampaignQuery(limit: 1));

        self::assertCount(2, $campaigns);
        self::assertSame(['1', '2'], array_map(static fn ($campaign): string => $campaign->id(), $campaigns));
        self::assertCount(2, $client->requests());
        self::assertArrayNotHasKey('after', $client->requests()[0]->query());
        self::assertSame('next-cursor', $client->requests()[1]->query()['after']);
        self::assertSame(1, $client->requests()[1]->query()['limit']);
    }

    public function testItRetrievesAnIndividualCampaign(): void
    {
        $client = CampaignFakeMetaClient::withResponses(new Response(200, [
            'id' => '120000000000001',
            'name' => 'One Campaign',
            'objective' => 'OUTCOME_SALES',
        ]));

        $campaign = $this->service($client)->find(
            '120000000000001',
            new Fields(['objective']),
        );

        self::assertSame('120000000000001', $campaign->id());
        self::assertSame('One Campaign', $campaign->name());
        self::assertSame('OUTCOME_SALES', $campaign->objective());
        self::assertSame('/v26.0/120000000000001', $client->requests()[0]->path());
        self::assertSame(['fields' => 'id,name,objective'], $client->requests()[0]->query());
    }

    #[DataProvider('emptyCampaignIdProvider')]
    public function testItRejectsAnEmptyCampaignIdBeforeMakingARequest(string $campaignId): void
    {
        $client = CampaignFakeMetaClient::withResponses();

        try {
            $this->service($client)->find($campaignId);
            self::fail('Expected invalid input to throw.');
        } catch (InvalidInputException $exception) {
            self::assertStringContainsString('Campaign ID', $exception->getMessage());
            self::assertSame([], $client->requests());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function emptyCampaignIdProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }

    public function testItRejectsUnsupportedCampaignFields(): void
    {
        $client = CampaignFakeMetaClient::withResponses();

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('daily_budget');

        $this->service($client)->all(new CampaignQuery(fields: new Fields(['daily_budget'])));
    }

    #[DataProvider('invalidQueryProvider')]
    public function testItRejectsInvalidCampaignQueries(array $arguments): void
    {
        $this->expectException(InvalidInputException::class);

        new CampaignQuery(...$arguments);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidQueryProvider(): iterable
    {
        yield 'unsupported effective status' => [['effectiveStatuses' => ['CAMPAIGN_PAUSED']]];
        yield 'zero limit' => [['limit' => 0]];
        yield 'negative limit' => [['limit' => -10]];
    }

    public function testItRejectsMalformedCollectionResponses(): void
    {
        $client = CampaignFakeMetaClient::withResponses(new Response(200, ['data' => 'invalid']));

        $this->expectException(UnexpectedResponseException::class);

        $this->service($client)->all();
    }

    public function testItRejectsRepeatedPaginationCursors(): void
    {
        $page = new Response(200, [
            'data' => [],
            'paging' => [
                'cursors' => ['after' => 'same-cursor'],
                'next' => 'https://graph.facebook.com/next-page',
            ],
        ]);
        $client = CampaignFakeMetaClient::withResponses($page, $page);

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('repeated pagination cursor');

        $this->service($client)->all();
    }

    #[DataProvider('apiErrorProvider')]
    public function testItMapsMetaErrorsToTypedExceptions(
        int $statusCode,
        int $metaCode,
        string $expectedException,
    ): void {
        $client = CampaignFakeMetaClient::withResponses(new Response($statusCode, [
            'error' => [
                'message' => 'Meta error',
                'type' => 'OAuthException',
                'code' => $metaCode,
                'fbtrace_id' => 'trace-id',
            ],
        ]));

        try {
            $this->service($client)->all();
            self::fail(sprintf('Expected %s to be thrown.', $expectedException));
        } catch (Throwable $exception) {
            self::assertInstanceOf($expectedException, $exception);
            self::assertSame($metaCode, $exception->metaErrorCode());
        }
    }

    /** @return iterable<string, array{int, int, class-string<Throwable>}> */
    public static function apiErrorProvider(): iterable
    {
        yield 'authentication' => [400, 190, AuthenticationException::class];
        yield 'permission' => [403, 200, PermissionException::class];
        yield 'not found' => [400, 803, ResourceNotFoundException::class];
        yield 'rate limit' => [429, 4, RateLimitException::class];
    }

    public function testItPreservesNetworkFailures(): void
    {
        $networkException = new NetworkException('Could not reach Meta.');
        $client = CampaignFakeMetaClient::withResponses($networkException);

        $this->expectExceptionObject($networkException);

        $this->service($client)->all();
    }

    public function testCampaignRequestsAndFailuresDoNotExposeTheToken(): void
    {
        $client = CampaignFakeMetaClient::withResponses(new Response(400, [
            'error' => [
                'message' => 'Rejected '.self::TOKEN,
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ]));

        try {
            $this->service($client)->all();
            self::fail('Expected authentication failure to throw.');
        } catch (AuthenticationException $exception) {
            self::assertStringNotContainsString(self::TOKEN, serialize($client->requests()));
            self::assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            self::assertStringNotContainsString(self::TOKEN, serialize($exception));
        }
    }

    private function service(CampaignFakeMetaClient $client): CampaignService
    {
        return new CampaignService($client, new MetaConfig(self::TOKEN, 'act_123456789', 'v26.0'));
    }

    /** @return array<string, string> */
    private function campaign(
        string $id,
        string $name,
        string $configuredStatus,
        string $effectiveStatus,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'status' => $configuredStatus,
            'configured_status' => $configuredStatus,
            'effective_status' => $effectiveStatus,
            'objective' => 'OUTCOME_SALES',
            'created_time' => '2026-09-01T00:00:00+0000',
            'updated_time' => '2026-09-02T00:00:00+0000',
        ];
    }
}

final class CampaignFakeMetaClient implements MetaClientInterface
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
