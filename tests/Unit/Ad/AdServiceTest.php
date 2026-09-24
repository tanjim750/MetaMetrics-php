<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Ad;

use MetaMetrics\Ad\AdQuery;
use MetaMetrics\Ad\AdService;
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

final class AdServiceTest extends TestCase
{
    public function testItRetrievesAdsWithSelectedFieldsAndPagination(): void
    {
        $client = AdFakeClient::withResponses(
            new Response(200, [
                'data' => [$this->ad('ad-1')],
                'paging' => [
                    'next' => 'https://graph.facebook.com/next',
                    'cursors' => ['after' => 'cursor-2'],
                ],
            ]),
            new Response(200, ['data' => [$this->ad('ad-2')]]),
        );
        $query = new AdQuery(
            fields: new Fields(['effective_status']),
            effectiveStatuses: ['ACTIVE', 'PAUSED', 'ACTIVE'],
            limit: 25,
        );

        $ads = $this->service($client)->all($query);

        self::assertSame(['ad-1', 'ad-2'], array_map(static fn ($ad): string => $ad->id(), $ads));
        self::assertSame('/v26.0/act_123/ads', $client->requests()[0]->path());
        self::assertSame([
            'fields' => 'id,name,adset_id,campaign_id,effective_status',
            'effective_status' => '["ACTIVE","PAUSED"]',
            'limit' => 25,
        ], $client->requests()[0]->query());
        self::assertSame('cursor-2', $client->requests()[1]->query()['after']);
    }

    #[DataProvider('parentPathProvider')]
    public function testItRetrievesAdsForParents(string $method, string $id, string $expectedPath): void
    {
        $client = AdFakeClient::withResponses(new Response(200, ['data' => []]));

        $this->service($client)->{$method}($id);

        self::assertSame($expectedPath, $client->requests()[0]->path());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function parentPathProvider(): iterable
    {
        yield 'campaign' => ['forCampaign', 'campaign-1', '/v26.0/campaign-1/ads'];
        yield 'Ad Set' => ['forAdSet', 'adset-1', '/v26.0/adset-1/ads'];
    }

    public function testItFindsAnAd(): void
    {
        $client = AdFakeClient::withResponses(new Response(200, $this->ad('ad-1')));

        $ad = $this->service($client)->find('ad-1', new Fields(['status']));

        self::assertSame('ad-1', $ad->id());
        self::assertSame('/v26.0/ad-1', $client->requests()[0]->path());
        self::assertSame('id,name,adset_id,campaign_id,status', $client->requests()[0]->query()['fields']);
    }

    public function testFindFailureIncludesAdIdentity(): void
    {
        $client = AdFakeClient::withResponses(new Response(404, [
            'error' => ['message' => 'Missing', 'code' => 803],
        ]));

        try {
            $this->service($client)->find('ad-404');
            self::fail('Expected missing Ad failure.');
        } catch (ResourceNotFoundException $exception) {
            self::assertSame('Ad', $exception->resourceType());
            self::assertSame('ad-404', $exception->resourceId());
        }
    }

    #[DataProvider('invalidCallProvider')]
    public function testItRejectsInvalidInputBeforeSending(string $method, array $arguments): void
    {
        $client = AdFakeClient::withResponses();

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
        yield 'empty Ad ID' => ['find', [' ']];
        yield 'empty campaign ID' => ['forCampaign', ['']];
        yield 'empty Ad Set ID' => ['forAdSet', [' ']];
        yield 'unsupported field' => ['all', [new AdQuery(fields: new Fields(['daily_budget']))]];
    }

    /** @return array<string, string> */
    private function ad(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'Ad '.$id,
            'adset_id' => 'adset-1',
            'campaign_id' => 'campaign-1',
            'status' => 'ACTIVE',
            'effective_status' => 'ACTIVE',
        ];
    }

    private function service(AdFakeClient $client): AdService
    {
        return new AdService($client, new MetaConfig('secret-token', 'act_123', 'v26.0'));
    }
}

final class AdFakeClient implements MetaClientInterface
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
            throw new \LogicException('No fake Ad response remains.');
        }

        return $response;
    }

    /** @return list<Request> */
    public function requests(): array
    {
        return $this->requests;
    }
}
