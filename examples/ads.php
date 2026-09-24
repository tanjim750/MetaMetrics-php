<?php

declare(strict_types=1);

use MetaMetrics\Client\MetaClient;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;

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

$config = new MetaConfig(
    accessToken: $credentials['accessToken'],
    adAccountId: $credentials['adAccountId'],
    apiVersion: $credentials['apiVersion'],
);
$mode = $argv[1] ?? 'all';
$resourceId = $argv[2] ?? null;

switch ($mode) {
    case 'all':
        $path = sprintf('/%s/%s/ads', $config->apiVersion(), $config->adAccountId());
        break;

    case 'campaign':
        $path = collectionPath($config, $resourceId, 'Campaign');
        break;

    case 'adset':
        $path = collectionPath($config, $resourceId, 'Ad Set');
        break;

    case 'find':
        $path = resourcePath($config, $resourceId, 'Ad');
        break;

    default:
        fwrite(STDERR, "Usage:\n");
        fwrite(STDERR, "  php examples/ads.php all\n");
        fwrite(STDERR, "  php examples/ads.php campaign <campaign-id>\n");
        fwrite(STDERR, "  php examples/ads.php adset <ad-set-id>\n");
        fwrite(STDERR, "  php examples/ads.php find <ad-id>\n");
        exit(1);
}

$response = (new MetaClient($config))->send(new Request(
    method: 'GET',
    path: $path,
    query: [
        'fields' => implode(',', [
            'id',
            'name',
            'adset_id',
            'campaign_id',
            'status',
            'configured_status',
            'effective_status',
            'created_time',
            'updated_time',
        ]),
    ],
));

fwrite(STDOUT, sprintf("HTTP %d\n", $response->statusCode()));
fwrite(STDOUT, ($response->rawBody() ?? '[empty response]').PHP_EOL);

exit($response->isSuccessful() ? 0 : 1);

function collectionPath(MetaConfig $config, ?string $id, string $label): string
{
    if ($id === null || trim($id) === '') {
        fwrite(STDERR, sprintf("Missing %s ID.\n", $label));
        exit(1);
    }

    return sprintf('/%s/%s/ads', $config->apiVersion(), rawurlencode($id));
}

function resourcePath(MetaConfig $config, ?string $id, string $label): string
{
    if ($id === null || trim($id) === '') {
        fwrite(STDERR, sprintf("Missing %s ID.\n", $label));
        exit(1);
    }

    return sprintf('/%s/%s', $config->apiVersion(), rawurlencode($id));
}
