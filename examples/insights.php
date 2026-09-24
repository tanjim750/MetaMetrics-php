<?php

declare(strict_types=1);

use MetaMetrics\Client\MetaClient;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\InsightsQuery;
use MetaMetrics\Insights\InsightsService;
use MetaMetrics\Insights\Metric\Metric;

require dirname(__DIR__).'/vendor/autoload.php';

$credentialsPath = dirname(__DIR__).'/tests/credentials.local.php';

if (!is_file($credentialsPath)) {
    fwrite(STDERR, "Missing tests/credentials.local.php.\n");
    exit(1);
}

$credentials = require $credentialsPath;

if (!is_array($credentials)) {
    fwrite(STDERR, "tests/credentials.local.php must return an array.\n");
    exit(1);
}

foreach (['accessToken', 'adAccountId', 'apiVersion'] as $key) {
    if (
        !isset($credentials[$key])
        || !is_string($credentials[$key])
        || trim($credentials[$key]) === ''
    ) {
        fwrite(STDERR, sprintf("Missing credential: %s.\n", $key));
        exit(1);
    }
}

$config = new MetaConfig(
    accessToken: $credentials['accessToken'],
    adAccountId: $credentials['adAccountId'],
    apiVersion: $credentials['apiVersion'],
);

$levelName = $argv[1] ?? 'account';
$since = $argv[2] ?? null;
$until = $argv[3] ?? null;
$entityId = $argv[4] ?? null;
$daily = ($argv[5] ?? 'aggregate') === 'daily';

if ($since === null || $until === null) {
    fwrite(STDERR, "Usage:\n");
    fwrite(
        STDERR,
        "  php examples/insights.php <level> <since> <until> [entity-id] [daily]\n"
    );
    exit(1);
}

$level = match ($levelName) {
    'account' => InsightsLevel::ACCOUNT,
    'campaign' => InsightsLevel::CAMPAIGN,
    'adset' => InsightsLevel::AD_SET,
    'ad' => InsightsLevel::AD,
    default => throw new InvalidArgumentException(
        'Unsupported Insights level.'
    ),
};

$query = new InsightsQuery(
    level: $level,
    dateRange: new DateRange($since, $until),
    metrics: [
        Metric::SPEND,
        Metric::PURCHASES,
        Metric::COST_PER_PURCHASE,
        Metric::PURCHASE_VALUE,
        Metric::ROAS,
        Metric::IMPRESSIONS,
        Metric::REACH,
        Metric::CLICKS,
        Metric::LINK_CLICKS,
        Metric::CPC,
        Metric::CTR,
        Metric::CPM,
    ],
    entityId: $entityId,
    daily: $daily,
);

$service = new InsightsService(
    new MetaClient($config),
    $config
);

$results = $service->get($query);

$output = array_map(
    static function ($result): array {
        return [
            'entity_id' => $result->entityId(),
            'entity_name' => $result->entityName(),
            'level' => $result->level()->value,

            'campaign_id' => $result->campaignId(),
            'adset_id' => $result->adSetId(),
            'ad_id' => $result->adId(),

            'date_start' => $result->dateStart(),
            'date_stop' => $result->dateStop(),

            'metrics' => $result->metrics(),

            'raw_cpr_data' => [
                'spend' => $result->rawData()->spend(),
                'actions' => $result->rawData()->actions(),
                'cost_per_action_type' =>
                    $result->rawData()->costPerActionType(),
                'optimization_goal' =>
                    $result->rawData()->optimizationGoal(),
                'promoted_object' =>
                    $result->rawData()->promotedObject(),
            ],
        ];
    },
    $results
);

fwrite(
    STDOUT,
    json_encode(
        $output,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR,
    ).PHP_EOL
);