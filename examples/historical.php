<?php

declare(strict_types=1);

use MetaMetrics\Client\MetaClient;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Historical\HistoricalQuery;
use MetaMetrics\Historical\HistoricalService;
use MetaMetrics\Insights\DateRange\DateRange;
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

$insights = new InsightsService(
    new MetaClient($config),
    $config,
);

$historical = new HistoricalService($insights);

$levelName = $argv[1] ?? null;
$since = $argv[2] ?? null;
$until = $argv[3] ?? null;
$parentId = $argv[4] ?? null;
$daily = ($argv[5] ?? 'aggregate') === 'daily';

if (
    $parentId !== null
    && trim($parentId) === ''
) {
    $parentId = null;
}

if (
    $levelName === null
    || $since === null
    || $until === null
) {
    fwrite(STDERR, "Usage:\n");
    fwrite(
        STDERR,
        "  php examples/historical.php <level> <since> <until> [parent-id] [daily]\n"
    );
    fwrite(STDERR, "\nExamples:\n");
    fwrite(
        STDERR,
        "  php examples/historical.php campaign 2026-09-01 2026-09-24\n"
    );
    fwrite(
        STDERR,
        "  php examples/historical.php adset 2026-09-01 2026-09-24 <campaign-id>\n"
    );
    fwrite(
        STDERR,
        "  php examples/historical.php ad 2026-09-01 2026-09-24 <ad-set-id>\n"
    );
    fwrite(
        STDERR,
        "  php examples/historical.php campaign 2026-09-01 2026-09-24 \"\" daily\n"
    );

    exit(1);
}

$level = match ($levelName) {
    'campaign' => HistoricalLevel::CAMPAIGN,
    'adset' => HistoricalLevel::AD_SET,
    'ad' => HistoricalLevel::AD,

    default => throw new InvalidArgumentException(
        'Supported levels: campaign, adset, ad.'
    ),
};

$query = new HistoricalQuery(
    level: $level,
    dateRange: new DateRange(
        $since,
        $until,
    ),
    parentId: $parentId,
    metrics: [
        Metric::SPEND,
        Metric::IMPRESSIONS,
        Metric::REACH,
        Metric::PURCHASES,
        Metric::ROAS,
    ],
    daily: $daily,
);

$results = $historical->get($query);

$output = array_map(
    static function ($result): array {
        return [
            'entity_id' => $result->entityId(),
            'entity_name' => $result->entityName(),
            'level' => $result->level()->value,

            'campaign_id' => $result->campaignId(),
            'adset_id' => $result->adSetId(),
            'ad_id' => $result->adId(),

            'requested_since' => $result->requestedSince(),
            'requested_until' => $result->requestedUntil(),

            'insight_since' => $result->insights()->dateStart(),
            'insight_until' => $result->insights()->dateStop(),

            'delivery_evidence' => $result
                ->deliveryEvidence()
                ->indicators(),

            'metrics' => $result
                ->insights()
                ->metrics(),
        ];
    },
    $results,
);

fwrite(
    STDOUT,
    json_encode(
        $output,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR,
    ).PHP_EOL,
);