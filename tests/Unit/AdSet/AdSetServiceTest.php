<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\AdSet;

use MetaMetrics\AdSet\AdSetQuery;
use MetaMetrics\AdSet\AdSetService;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\ResourceNotFoundException;
use MetaMetrics\Query\Fields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class AdSetServiceTest extends TestCase
{
    public function testItRetrievesAdSetsWithSelectedFieldsAndPagination(): void
    {
        $client = AdSetFakeClient::withResponses(
            new Response(200, [
                'data' => [$this->adSet('adset-1')],
                'paging' => [
                    'next' => 'https://graph.facebook.com/next',
                    'cursors' => ['after' => 'cursor-2'],
                ],
            ]),
            new Response(200, ['data' => [$this->adSet('adset-2')]]),
        );
        $query = new AdSetQuery(
            fields: new Fields(['optimization_goal']),
            effectiveStatuses: ['ACTIVE', 'PAUSED', 'ACTIVE'],
            completed: false,
            limit: 25,
        );

        $adSets = $this->service($client)->all($query);

        self::assertSame(['adset-1', 'adset-2'], array_map(static fn ($adSet): string => $adSet->id(), $adSets));
        self::assertSame('/v26.0/act_123/adsets', $client->requests()[0]->path());
        self::assertSame([
            'fields' => 'id,name,campaign_id,optimization_goal',
            'effective_status' => '["ACTIVE","PAUSED"]',
            'is_completed' => false,
            'limit' => 25,
        ], $client->requests()[0]->query());
        self::assertSame('cursor-2', $client->requests()[1]->query()['after']);
    }

    public function testItRetrievesAdSetsForACampaign(): void
    {
        $client = AdSetFakeClient::withResponses(new Response(200, ['data' => []]));

        $this->service($client)->forCampaign('campaign-1');

        self::assertSame('/v26.0/campaign-1/adsets', $client->requests()[0]->path());
    }

    public function testItFindsAnAdSet(): void
    {
        $client = AdSetFakeClient::withResponses(new Response(200, $this->adSet('adset-1')));

        $adSet = $this->service($client)->find('adset-1', new Fields(['billing_event']));

        self::assertSame('adset-1', $adSet->id());
        self::assertSame('/v26.0/adset-1', $client->requests()[0]->path());
        self::assertSame('id,name,campaign_id,billing_event', $client->requests()[0]->query()['fields']);
    }

    public function testFindFailureIncludesAdSetIdentity(): void
    {
        $client = AdSetFakeClient::withResponses(new Response(404, [
            'error' => ['message' => 'Missing', 'code' => 803],
        ]));

        try {
            $this->service($client)->find('adset-404');
            self::fail('Expected missing Ad Set failure.');
        } catch (ResourceNotFoundException $exception) {
            self::assertSame('Ad Set', $exception->resourceType());
            self::assertSame('adset-404', $exception->resourceId());
        }
    }

    #[DataProvider('invalidCallProvider')]
    public function testItRejectsInvalidInputBeforeSending(string $method, array $arguments): void
    {
        $client = AdSetFakeClient::withResponses();

        try {
            $this->service($client)->{$method}(...$arguments);
            self::fail('Expected invalid input.');
        } catch (InvalidInputException) {
            self::assertSame([], $client->requests());
        }
    }

    /** @return iterable<string, array{string, array<mixed>}> */
    public static function invalidCallProvider(): iterable
    {
        yield 'empty Ad Set ID' => ['find', [' ']];
        yield 'empty campaign ID' => ['forCampaign', ['']];
        yield 'unsupported field' => ['all', [new AdSetQuery(fields: new Fields(['creative']))]];
    }

    /** @return array<string, string> */
    private function adSet(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'Ad Set '.$id,
            'campaign_id' => 'campaign-1',
            'status' => 'ACTIVE',
            'effective_status' => 'ACTIVE',
            'optimization_goal' => 'OFFSITE_CONVERSIONS',
            'billing_event' => 'IMPRESSIONS',
        ];
    }

    private function service(AdSetFakeClient $client): AdSetService
    {
        return new AdSetService($client, new MetaConfig('secret-token', 'act_123', 'v26.0'));
    }
}

final class AdSetFakeClient implements MetaClientInterface
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
            throw new \LogicException('No fake Ad Set response remains.');
        }

        return $response;
    }

    /** @return list<Request> */
    public function requests(): array
    {
        return $this->requests;
    }
}
