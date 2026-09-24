<?php

declare(strict_types=1);

use MetaMetrics\Client\MetaClient;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsDTO;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\InsightsQuery;
use MetaMetrics\Insights\InsightsService;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricAggregationResult;
use MetaMetrics\Insights\Metric\MetricAggregator;
use MetaMetrics\Insights\Metric\MetricRegistry;

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
    if (!isset($credentials[$key]) || !is_string($credentials[$key]) || trim($credentials[$key]) === '') {
        fwrite(STDERR, sprintf("Missing credential: %s.\n", $key));
        exit(1);
    }
}

$levelName = $argv[1] ?? null;
$since = $argv[2] ?? null;
$until = $argv[3] ?? null;
$entityId = $argv[4] ?? null;

if ($entityId !== null && trim($entityId) === '') {
    $entityId = null;
}

if ($levelName === null || $since === null || $until === null) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php examples/matrics.php <level> <since> <until> [entity-id]\n");
    fwrite(STDERR, "\nSupported levels: account, campaign, adset, ad.\n");
    fwrite(STDERR, "\nExamples:\n");
    fwrite(STDERR, "  php examples/matrics.php account 2026-09-01 2026-09-24\n");
    fwrite(STDERR, "  php examples/matrics.php campaign 2026-09-01 2026-09-24\n");
    fwrite(STDERR, "  php examples/matrics.php adset 2026-09-01 2026-09-24 <campaign-id>\n");
    fwrite(STDERR, "  php examples/matrics.php ad 2026-09-01 2026-09-24 <ad-set-id>\n");
    exit(1);
}

$level = match ($levelName) {
    'account' => InsightsLevel::ACCOUNT,
    'campaign' => InsightsLevel::CAMPAIGN,
    'adset' => InsightsLevel::AD_SET,
    'ad' => InsightsLevel::AD,
    default => throw new InvalidArgumentException('Supported levels: account, campaign, adset, ad.'),
};

$metrics = Metric::cases();
$dateRange = new DateRange($since, $until);
$config = new MetaConfig(
    accessToken: $credentials['accessToken'],
    adAccountId: $credentials['adAccountId'],
    apiVersion: $credentials['apiVersion'],
);
$registry = new MetricRegistry();
$service = new InsightsService(
    new MetaClient($config),
    $config,
    registry: $registry,
);

$periodRows = $service->get(new InsightsQuery(
    level: $level,
    dateRange: $dateRange,
    metrics: $metrics,
    entityId: $entityId,
));
$dailyRows = $service->get(new InsightsQuery(
    level: $level,
    dateRange: $dateRange,
    metrics: $metrics,
    entityId: $entityId,
    daily: true,
));

$dailyByEntity = [];

foreach ($dailyRows as $row) {
    $dailyByEntity[$row->entityId()][] = $row;
}

$aggregator = new MetricAggregator($registry);
$localAggregates = [];

foreach ($dailyByEntity as $rows) {
    $aggregate = $aggregator->aggregate($rows, $metrics);

    if ($aggregate !== null) {
        $localAggregates[] = aggregationOutput($aggregate);
    }
}

$definitions = [];

foreach ($registry->definitions() as $definition) {
    $metric = $definition->metric();
    $definitions[$metric->value] = [
        'type' => $definition->type()->value,
        'unit' => $definition->unit()->value,
        'aggregation' => $definition->aggregation()->value,
        'dependencies' => array_map(
            static fn (Metric $dependency): string => $dependency->value,
            $definition->dependencies(),
        ),
        'meta_fields' => $definition->metaFields(),
        'requires_actions' => $registry->requiresActions($metric),
        'requires_action_values' => $registry->requiresActionValues($metric),
    ];
}

$output = [
    'request' => [
        'level' => $level->value,
        'since' => $dateRange->since(),
        'until' => $dateRange->until(),
        'entity_id' => $entityId,
    ],
    'registry' => $definitions,
    'meta_period_rows' => array_map('insightsOutput', $periodRows),
    'daily_rows' => array_map('insightsOutput', $dailyRows),
    'locally_aggregated_daily_rows' => $localAggregates,
    'notes' => [
        'meta_period_rows contain Meta-reported period Reach.',
        'locally aggregated Reach is null when more than one daily row is combined.',
        'derived local metrics are recalculated from foundational totals.',
    ],
];

fwrite(
    STDOUT,
    json_encode(
        $output,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ).PHP_EOL,
);

/** @return array<string, mixed> */
function insightsOutput(InsightsDTO $result): array
{
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
        'foundational_metrics' => $result->foundationalMetrics(),
    ];
}

/** @return array<string, mixed> */
function aggregationOutput(MetricAggregationResult $result): array
{
    return [
        'entity_id' => $result->entityId(),
        'level' => $result->level()->value,
        'date_start' => $result->dateRange()->since(),
        'date_stop' => $result->dateRange()->until(),
        'metrics' => $result->metrics(),
    ];
}
