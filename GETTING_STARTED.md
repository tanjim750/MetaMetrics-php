# Installation and Usage

This guide covers installing MetaMetrics, supplying credentials, creating the client, and using the main retrieval and analytics services.

## Requirements

- PHP 8.2 or newer
- PHP cURL extension
- Composer
- A Meta access token with access to the required Ad Account
- A Meta Ad Account ID
- A Meta Marketing API version

MetaMetrics accepts an existing access token. It does not implement Facebook Login, OAuth redirects, or token generation.

## Installation

### Current development checkout

MetaMetrics is not yet published on Packagist. To consume a local checkout from another Composer project, add a path repository to that project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../meta-matrics"
        }
    ]
}
```

Adjust the path so it points to the directory containing the MetaMetrics `composer.json`, then run:

```bash
composer require metametrics/metametrics:@dev
```

To work inside the MetaMetrics repository itself, install its development dependencies:

```bash
composer install
```

## Configuration

Create an immutable `MetaConfig` using credentials loaded by your application:

```php
<?php

use MetaMetrics\Config\MetaConfig;

$config = new MetaConfig(
    accessToken: $accessToken,
    adAccountId: 'act_123456789',
    apiVersion: 'v26.0',
);
```

Numeric account IDs are accepted and normalized:

```php
$config = new MetaConfig(
    accessToken: $accessToken,
    adAccountId: '123456789',
    apiVersion: 'v26.0',
);

echo $config->adAccountId(); // act_123456789
```

The application is responsible for loading secrets from its environment, secret manager, or framework configuration. MetaMetrics does not read `.env`, `$_ENV`, or framework configuration directly.

Never commit a real access token. The repository's manual examples read `tests/credentials.local.php`, which is ignored by Git and should contain:

```php
<?php

return [
    'accessToken' => 'YOUR_META_ACCESS_TOKEN',
    'adAccountId' => 'act_123456789',
    'apiVersion' => 'v26.0',
];
```

## Create The API

`MetaAds` is the convenience entry point. It creates all services with one shared client and configuration:

```php
<?php

use MetaMetrics\Config\MetaConfig;
use MetaMetrics\MetaAds;

require __DIR__.'/vendor/autoload.php';

$config = new MetaConfig(
    accessToken: $accessToken,
    adAccountId: 'act_123456789',
    apiVersion: 'v26.0',
);

$meta = new MetaAds($config);
```

Individual services can also be constructed directly when dependency customization is required.

## Validate Authentication

Validation performs a lightweight request against the configured Ad Account:

```php
$result = $meta->authentication()->validate();

echo $result->accountId();
var_dump($result->authenticated());
var_dump($result->accountAccessible());
```

Authentication, permission, account-access, network, and rate-limit failures are reported through typed exceptions.

## Retrieve The Ad Account

```php
$account = $meta->account()->get();

echo $account->id();
echo $account->name();
echo $account->accountId();
echo $account->accountStatus();
echo $account->currency();
echo $account->timezoneName();
```

The request retrieves:

```text
id,name,account_id,account_status,currency,timezone_name
```

## Campaigns

Retrieve all Campaigns:

```php
$campaigns = $meta->campaigns()->all();

foreach ($campaigns as $campaign) {
    echo $campaign->id().' '.$campaign->name().PHP_EOL;
}
```

Retrieve one Campaign:

```php
$campaign = $meta->campaigns()->find('CAMPAIGN_ID');
```

Select fields and query options:

```php
use MetaMetrics\Campaign\CampaignQuery;
use MetaMetrics\Query\Fields;

$campaigns = $meta->campaigns()->all(new CampaignQuery(
    fields: new Fields(['objective', 'effective_status']),
    effectiveStatuses: ['ACTIVE', 'PAUSED'],
    completed: false,
    limit: 100,
));
```

Required identity fields are added automatically.

## Ad Sets

Retrieve every Ad Set in the configured account:

```php
$adSets = $meta->adSets()->all();
```

Retrieve Ad Sets belonging to one Campaign:

```php
$adSets = $meta->adSets()->forCampaign('CAMPAIGN_ID');
```

Retrieve one Ad Set:

```php
$adSet = $meta->adSets()->find('AD_SET_ID');
```

Use `AdSetQuery` for selected fields, statuses, completion state, and limits.

## Ads

Retrieve every Ad in the configured account:

```php
$ads = $meta->ads()->all();
```

Scope Ads to a Campaign or Ad Set:

```php
$campaignAds = $meta->ads()->forCampaign('CAMPAIGN_ID');
$adSetAds = $meta->ads()->forAdSet('AD_SET_ID');
```

Retrieve one Ad:

```php
$ad = $meta->ads()->find('AD_ID');
```

Use `AdQuery` for selected fields, effective statuses, and limits.

## Insights

Insights support Account, Campaign, Ad Set, and Ad levels.

```php
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\InsightsQuery;
use MetaMetrics\Insights\Metric\Metric;

$query = new InsightsQuery(
    level: InsightsLevel::CAMPAIGN,
    dateRange: new DateRange('2026-09-01', '2026-09-24'),
    metrics: [
        Metric::SPEND,
        Metric::PURCHASES,
        Metric::COST_PER_PURCHASE,
        Metric::PURCHASE_VALUE,
        Metric::ROAS,
        Metric::IMPRESSIONS,
        Metric::CLICKS,
        Metric::CTR,
    ],
);

$rows = $meta->insights()->get($query);

foreach ($rows as $row) {
    echo $row->entityId().PHP_EOL;
    print_r($row->metrics());
}
```

### Daily results

Set `daily: true` to request one-day reporting rows:

```php
$query = new InsightsQuery(
    level: InsightsLevel::ACCOUNT,
    dateRange: new DateRange('2026-09-01', '2026-09-24'),
    metrics: [Metric::SPEND, Metric::IMPRESSIONS, Metric::CTR],
    daily: true,
);
```

Without `daily: true`, Meta returns aggregate rows for the requested period.

### Entity scope and filters

Use `entityId` to run Insights against a specific Meta object while retaining the selected reporting level:

```php
$query = new InsightsQuery(
    level: InsightsLevel::AD_SET,
    dateRange: new DateRange('2026-09-01', '2026-09-24'),
    metrics: [Metric::SPEND],
    entityId: 'CAMPAIGN_ID',
);
```

Typed filters are available for supported Insights fields:

```php
use MetaMetrics\Insights\InsightsFilterField;
use MetaMetrics\Query\Filter;
use MetaMetrics\Query\FilterOperator;

$query = new InsightsQuery(
    level: InsightsLevel::CAMPAIGN,
    dateRange: new DateRange('2026-09-01', '2026-09-24'),
    metrics: [Metric::SPEND],
    filters: [
        new Filter(
            InsightsFilterField::CAMPAIGN_ID->value,
            FilterOperator::IN,
            ['CAMPAIGN_ID'],
        ),
    ],
);
```

## Historical Delivery

Historical retrieval keeps only Campaigns, Ad Sets, or Ads with delivery evidence during the requested period.

```php
use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Historical\HistoricalQuery;

$results = $meta->historical()->get(new HistoricalQuery(
    level: HistoricalLevel::CAMPAIGN,
    dateRange: new DateRange('2026-09-01', '2026-09-24'),
    metrics: [Metric::SPEND, Metric::PURCHASES, Metric::ROAS],
));

foreach ($results as $result) {
    echo $result->entityId().PHP_EOL;
    print_r($result->deliveryEvidence()->indicators());
    print_r($result->insights()->metrics());
}
```

For Ad Set and Ad queries, `parentId` can scope the request to a Campaign or Ad Set:

```php
$results = $meta->historical()->get(new HistoricalQuery(
    level: HistoricalLevel::AD_SET,
    dateRange: new DateRange('2026-09-01', '2026-09-24'),
    parentId: 'CAMPAIGN_ID',
    metrics: [Metric::SPEND],
    daily: true,
));
```

## Historical Hierarchy

Build a Campaign to Ad Set to Ad hierarchy for a date range:

```php
$hierarchy = $meta->hierarchy()->historical(
    new DateRange('2026-09-01', '2026-09-24'),
    metrics: [Metric::SPEND, Metric::ROAS],
);

foreach ($hierarchy->campaigns() as $campaignNode) {
    echo $campaignNode->entityId().PHP_EOL;

    foreach ($campaignNode->children() as $adSetNode) {
        echo '  '.$adSetNode->entityId().PHP_EOL;

        foreach ($adSetNode->children() as $adNode) {
            echo '    '.$adNode->entityId().PHP_EOL;
        }
    }
}
```

Use `unattachedAdSets()` and `unattachedAds()` to inspect delivered entities whose parent row was not returned for the same reporting period.

## Aggregate Daily Rows Locally

`MetricAggregator` sums foundational metrics and recalculates derived metrics such as CTR, CPC, CPM, and ROAS.

```php
use MetaMetrics\Insights\Metric\MetricAggregator;

$aggregate = (new MetricAggregator())->aggregate(
    $dailyRowsForOneEntity,
    [Metric::SPEND, Metric::CLICKS, Metric::CTR],
);

if ($aggregate !== null) {
    print_r($aggregate->metrics());
}
```

All rows passed to one aggregation must represent the same entity and level, and their reporting periods must not overlap. Reach is not summed across multiple rows because that would double-count people.

## Error Handling

Catch specific exceptions when the application has a dedicated response, or catch `MetaAdsException` for all library failures:

```php
use MetaMetrics\Exception\AuthenticationException;
use MetaMetrics\Exception\MetaAdsException;
use MetaMetrics\Exception\RateLimitException;

try {
    $campaigns = $meta->campaigns()->all();
} catch (AuthenticationException $exception) {
    // Replace or renew the token through the application's Meta auth flow.
} catch (RateLimitException $exception) {
    $retryAfter = $exception->retryAfterSeconds();
    $retryable = $exception->isRetryable();
} catch (MetaAdsException $exception) {
    $safeContext = $exception->context();
}
```

Exception context is sanitized for application logging. Do not log credentials, request authorization headers, or unfiltered raw responses.

## Tracking URLs

`TrackingUrlParser` extracts identifiers from landing-page query parameters without making a Meta API request:

```php
use MetaMetrics\Tracking\TrackingUrlParser;

$identifiers = (new TrackingUrlParser())->parse(
    'https://example.com/product?utm_id=111&utm_term=222&utm_content=333'
    .'&utm_source=fb&utm_medium=paid',
);

echo $identifiers->campaignId(); // 111
echo $identifiers->adSetId();    // 222
echo $identifiers->adId();       // 333
```

The default mapping is:

```text
utm_id      -> Campaign ID
utm_term    -> Ad Set ID
utm_content -> Ad ID
utm_source  -> source
utm_medium  -> medium
```

The Ad Set and Ad meanings are this library's default tracking convention, not universal UTM semantics. Parsing does not prove that a value belongs to a real Meta entity and does not perform attribution.

Override parameter names when your tracking setup uses another convention:

```php
$identifiers = (new TrackingUrlParser())->parse(
    'https://example.com/product?campaign_id=111&adset_id=222&ad_id=333',
    [
        'campaignId' => 'campaign_id',
        'adSetId' => 'adset_id',
        'adId' => 'ad_id',
    ],
);
```

When `utm_id` is absent, a numeric `utm_campaign` can be used as a Campaign ID fallback. Campaign names and `fbclid` are not interpreted as Meta entity IDs.

## Raw Responses

Use the low-level client only when normalized services do not provide the required response access:

```php
use MetaMetrics\Client\Request;

$response = $meta->client()->send(new Request(
    method: 'GET',
    path: sprintf('/%s/%s', $config->apiVersion(), $config->adAccountId()),
    query: ['fields' => 'id,name,currency'],
));

if ($response->isSuccessful()) {
    $decoded = $response->body();
    $rawJson = $response->rawBody();
} else {
    $status = $response->statusCode();
    $errorBody = $response->body();
}
```

Direct client calls return unsuccessful responses rather than mapping them to typed service exceptions.

## Pagination

Collection and Insights services automatically follow Meta cursor pagination. Callers receive one combined result list. If a later page fails, the service throws the relevant exception rather than returning incomplete data.

## Run Tests

Unit tests do not require Meta credentials:

```bash
composer test
```

The authentication integration test uses `tests/credentials.local.php`:

```bash
composer test:integration
```

## Run The Examples

After creating `tests/credentials.local.php`, the bundled scripts can make manual requests with your configured account:

```bash
php examples/campaigns.php
php examples/adsets.php all
php examples/ads.php all
php examples/insights.php account 2026-09-01 2026-09-24
php examples/historical.php campaign 2026-09-01 2026-09-24
php examples/matrics.php account 2026-09-01 2026-09-24
```

The date range must use `YYYY-MM-DD`. These commands make real Meta API requests and may be subject to account permissions and rate limits.
