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
$campaignId = $argv[1] ?? null;
$path = $campaignId === null
    ? sprintf('/%s/%s/campaigns', $config->apiVersion(), $config->adAccountId())
    : sprintf('/%s/%s', $config->apiVersion(), rawurlencode($campaignId));

$response = (new MetaClient($config))->send(new Request(
    method: 'GET',
    path: $path,
    query: [
        'fields' => implode(',', [
            'id',
            'name',
            'status',
            'configured_status',
            'effective_status',
            'objective',
            'created_time',
            'updated_time',
        ]),
    ],
));

fwrite(STDOUT, sprintf("HTTP %d\n", $response->statusCode()));
fwrite(STDOUT, ($response->rawBody() ?? '[empty response]').PHP_EOL);

exit($response->isSuccessful() ? 0 : 1);
