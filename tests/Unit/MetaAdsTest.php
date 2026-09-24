<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit;

use MetaMetrics\Account\AccountService;
use MetaMetrics\Ad\AdService;
use MetaMetrics\AdSet\AdSetService;
use MetaMetrics\Authentication\AuthenticationService;
use MetaMetrics\Campaign\CampaignService;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Hierarchy\HierarchyService;
use MetaMetrics\Historical\HistoricalService;
use MetaMetrics\Insights\InsightsService;
use MetaMetrics\MetaAds;
use PHPUnit\Framework\TestCase;

final class MetaAdsTest extends TestCase
{
    public function testItExposesServicesBackedByTheInjectedClient(): void
    {
        $client = new FacadeFakeClient(new Response(200, [
            'id' => 'act_123',
            'name' => 'Facade Account',
            'account_id' => '123',
        ]));
        $meta = new MetaAds(
            new MetaConfig('test-token', 'act_123', 'v26.0'),
            $client,
        );

        self::assertSame($client, $meta->client());
        self::assertInstanceOf(AuthenticationService::class, $meta->authentication());
        self::assertInstanceOf(AccountService::class, $meta->account());
        self::assertInstanceOf(CampaignService::class, $meta->campaigns());
        self::assertInstanceOf(AdSetService::class, $meta->adSets());
        self::assertInstanceOf(AdService::class, $meta->ads());
        self::assertInstanceOf(InsightsService::class, $meta->insights());
        self::assertInstanceOf(HistoricalService::class, $meta->historical());
        self::assertInstanceOf(HierarchyService::class, $meta->hierarchy());

        self::assertSame('act_123', $meta->account()->get()->id());
        self::assertSame(1, $client->requests());
    }
}

final class FacadeFakeClient implements MetaClientInterface
{
    private int $requests = 0;

    public function __construct(private readonly Response $response)
    {
    }

    public function send(Request $request): Response
    {
        ++$this->requests;

        return $this->response;
    }

    public function requests(): int
    {
        return $this->requests;
    }
}
