<?php

declare(strict_types=1);

namespace MetaMetrics;

use MetaMetrics\Account\AccountService;
use MetaMetrics\Ad\AdService;
use MetaMetrics\AdSet\AdSetService;
use MetaMetrics\Authentication\AuthenticationService;
use MetaMetrics\Campaign\CampaignService;
use MetaMetrics\Client\MetaClient;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Hierarchy\HierarchyService;
use MetaMetrics\Historical\HistoricalService;
use MetaMetrics\Insights\InsightsService;

final readonly class MetaAds
{
    private MetaClientInterface $client;

    public function __construct(
        private MetaConfig $config,
        ?MetaClientInterface $client = null,
    ) {
        $this->client = $client ?? new MetaClient($this->config);
    }

    public function authentication(): AuthenticationService
    {
        return new AuthenticationService($this->client, $this->config);
    }

    public function account(): AccountService
    {
        return new AccountService($this->client, $this->config);
    }

    public function campaigns(): CampaignService
    {
        return new CampaignService($this->client, $this->config);
    }

    public function adSets(): AdSetService
    {
        return new AdSetService($this->client, $this->config);
    }

    public function ads(): AdService
    {
        return new AdService($this->client, $this->config);
    }

    public function insights(): InsightsService
    {
        return new InsightsService($this->client, $this->config);
    }

    public function historical(): HistoricalService
    {
        return new HistoricalService($this->insights());
    }

    public function hierarchy(): HierarchyService
    {
        return new HierarchyService($this->historical());
    }

    public function client(): MetaClientInterface
    {
        return $this->client;
    }
}
