# MetaMetrics

**MetaMetrics** is a framework-independent PHP library for fetching, normalizing, and analyzing Meta Ads data through the Meta Marketing API.

It provides a reusable abstraction for working with **Ad Accounts, Campaigns, Ad Sets, Ads, and performance insights** without requiring applications to directly handle Meta's raw API structure, pagination, or metric parsing.

## Installation

MetaMetrics requires PHP 8.2 or newer and the cURL extension. The package is not yet published on Packagist.

To use the current checkout from another Composer project, register it as a path repository:

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

Then install the development package:

```bash
composer require metametrics/metametrics:@dev
```

Inside this repository, install development dependencies with:

```bash
composer install
```

## Quick Start

The consumer application owns credential loading. Pass the resulting strings into `MetaConfig`; the library does not read environment variables or framework configuration directly.

See the complete [installation and usage guide](GETTING_STARTED.md) for service queries, Insights, historical delivery, hierarchy results, and error handling.

```php
<?php

use MetaMetrics\Config\MetaConfig;
use MetaMetrics\MetaAds;

require __DIR__.'/vendor/autoload.php';

$meta = new MetaAds(new MetaConfig(
    accessToken: $accessToken,
    adAccountId: 'act_123456789',
    apiVersion: 'v26.0',
));

$connection = $meta->authentication()->validate();
$account = $meta->account()->get();
$campaigns = $meta->campaigns()->all();
```

Catch a specific exception when the application has a dedicated recovery path, or catch `MetaAdsException` for all library failures:

```php
use MetaMetrics\Exception\MetaAdsException;
use MetaMetrics\Exception\RateLimitException;

try {
    $campaigns = $meta->campaigns()->all();
} catch (RateLimitException $exception) {
    $retryAfter = $exception->retryAfterSeconds();
} catch (MetaAdsException $exception) {
    $context = $exception->context();
}
```

## Features & Capabilities

* Meta Marketing API authentication and configuration
* Ad Account information retrieval
* Campaign, Ad Set, and Ad data retrieval
* Campaign → Ad Set → Ad hierarchy support
* Date-range based historical insights
* Historical delivery discovery
* Account, Campaign, Ad Set, and Ad level analytics
* Aggregate and daily performance breakdowns
* Automatic pagination handling
* Flexible filtering and metric selection
* Configurable tracking URL identifier parsing
* Normalized, PHP-friendly data output
* Raw Meta response access when needed
* Structured API and error handling
* Rate-limit aware architecture
* Sanitized diagnostic context for optional application logging
* Framework-independent and Composer-friendly design

### Supported Analytics

Designed to work with key Meta Ads metrics such as:

* Spend
* Purchases
* Cost Per Purchase (CPP)
* Purchase Conversion Value
* ROAS
* Impressions
* Reach
* Clicks / Link Clicks
* CPC
* CTR
* CPM

## Historical Analytics

MetaMetrics can discover which Campaigns, Ad Sets, and Ads actually delivered during a requested period, regardless of their current status.

For example:

```text
Requested Period: September 1–24

Campaign A → Created before September, delivered Sep 1–10
Campaign B → Created Sep 10, delivered Sep 10–24
Campaign C → Currently active but had no delivery during the period
```

MetaMetrics can identify the historically relevant entities and return performance data scoped specifically to **September 1–24**.

## Uses

MetaMetrics can be used as the Meta Ads data layer for:

* Analytics dashboards
* Marketing reporting systems
* Ecommerce analytics applications
* Internal business intelligence tools
* Automated advertising reports
* Campaign performance monitoring
* Historical advertising analysis
* Custom Meta Ads integrations

Applications can combine MetaMetrics data with their own **orders, products, customers, attribution data, or business logic** without coupling those concerns to the library.

## Framework Support

MetaMetrics is designed for use with:

* Plain PHP
* Laravel
* Symfony
* Other Composer-based PHP applications

The core package does not depend on a framework, cache implementation, logger, or environment loader. Applications may log the sanitized context exposed by MetaMetrics exceptions through their own logging stack.

## Raw Responses

Normalized services are the default public workflow. When direct response inspection is required, use the shared low-level client:

```php
use MetaMetrics\Client\Request;

$response = $meta->client()->send(new Request(
    method: 'GET',
    path: '/v26.0/act_123456789',
    query: ['fields' => 'id,name,currency'],
));

if ($response->isSuccessful()) {
    $decoded = $response->body();
    $raw = $response->rawBody();
} else {
    $status = $response->statusCode();
    $error = $response->body();
}
```

Unlike normalized services, direct client calls return unsuccessful responses instead of mapping them to typed MetaMetrics exceptions. Never write raw responses to logs without applying the application's credential and privacy controls.

## Testing

```bash
composer test
```

The unit suite uses fake Meta clients and does not require credentials. Live authentication checks use the separate integration configuration described in `docs/authentication.md`.

## Scope

MetaMetrics focuses on:

```text
Meta Marketing API
        ↓
Fetch Ads Data
        ↓
Historical Insights
        ↓
Parse & Normalize
        ↓
Calculate Useful Metrics
        ↓
PHP-friendly Data
```

It does not provide dashboards, HTTP endpoints, ecommerce management, product management, or application-specific business logic.

> **MetaMetrics understands Meta Ads; your application understands your business.**

## Status

🚧 **Under Development**

The architecture and public API are currently being developed.

## License

Open-source. See the `LICENSE` file for details.
