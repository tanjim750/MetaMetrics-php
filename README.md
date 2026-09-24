# MetaMetrics

**MetaMetrics** is a framework-independent PHP library for fetching, normalizing, and analyzing Meta Ads data through the Meta Marketing API.

It provides a reusable abstraction for working with **Ad Accounts, Campaigns, Ad Sets, Ads, and performance insights** without requiring applications to directly handle Meta's raw API structure, pagination, or metric parsing.

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
* Normalized, PHP-friendly data output
* Raw Meta response access when needed
* Structured API and error handling
* Rate-limit aware architecture
* Optional caching and logging
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
