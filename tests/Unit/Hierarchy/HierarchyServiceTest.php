<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Hierarchy;

use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Hierarchy\HierarchyService;
use MetaMetrics\Historical\HistoricalService;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsService;
use PHPUnit\Framework\TestCase;

final class HierarchyServiceTest extends TestCase
{
    public function testItBuildsHistoricalHierarchyFromIndependentLevelEvidence(): void
    {
        /** @var array<string, list<array<string, mixed>>> $fixture */
        $fixture = require dirname(__DIR__, 2).'/Fixtures/MetaResponses/historical_hierarchy.php';
        $client = new HierarchyFakeMetaClient(
            new Response(200, ['data' => $fixture['campaigns']]),
            new Response(200, ['data' => $fixture['adsets']]),
            new Response(200, ['data' => $fixture['ads']]),
        );
        $config = new MetaConfig('hierarchy-secret', 'act_123', 'v26.0');
        $service = new HierarchyService(new HistoricalService(new InsightsService($client, $config)));

        $result = $service->historical(new DateRange('2026-09-01', '2026-09-24'));

        self::assertCount(1, $result->campaigns());
        self::assertSame('campaign-1', $result->campaigns()[0]->entityId());
        self::assertCount(1, $result->campaigns()[0]->children());
        self::assertSame('adset-1', $result->campaigns()[0]->children()[0]->entityId());
        self::assertCount(1, $result->campaigns()[0]->children()[0]->children());
        self::assertSame('ad-1', $result->campaigns()[0]->children()[0]->children()[0]->entityId());

        self::assertSame(
            ['adset-without-delivered-campaign'],
            array_map(static fn ($node): string => $node->entityId(), $result->unattachedAdSets()),
        );
        self::assertSame(
            ['ad-without-delivered-adset'],
            array_map(static fn ($node): string => $node->entityId(), $result->unattachedAds()),
        );

        self::assertCount(3, $client->requests());
        self::assertSame(['campaign', 'adset', 'ad'], array_map(
            static fn (Request $request): mixed => $request->query()['level'],
            $client->requests(),
        ));
    }
}

final class HierarchyFakeMetaClient implements MetaClientInterface
{
    /** @var list<Response> */
    private array $responses;

    /** @var list<Request> */
    private array $requests = [];

    public function __construct(Response ...$responses)
    {
        $this->responses = $responses;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        return array_shift($this->responses)
            ?? throw new \LogicException('The fake Meta client has no response queued.');
    }

    /** @return list<Request> */
    public function requests(): array
    {
        return $this->requests;
    }
}
