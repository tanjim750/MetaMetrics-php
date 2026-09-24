# MetaMetrics — Historical Data Implementation Instructions

## Implementation Status

The Historical Data module is implemented in these completed slices:

- [x] `HistoricalLevel` limits discovery to Campaign, Ad Set, and Ad.
- [x] `HistoricalQuery` carries the mandatory `DateRange`, optional parent scope,
  filters, requested metrics, daily mode, and page limit.
- [x] `DeliveryDiscovery` centrally defines historical delivery evidence.
- [x] `DeliveryEvidence` records the positive indicators supporting inclusion.
- [x] `HistoricalResult` wraps normalized `InsightsDTO` data with the requested
  reporting period and delivery evidence.
- [x] `HistoricalService` performs level-based discovery through
  `InsightsService`, without status, creation-date, or entity-detail lookups.
- [x] Aggregate and explicitly requested daily discovery are supported.
- [x] Parent IDs, shared filtering, pagination, and the existing exception
  behavior are preserved.
- [x] Historical Ad Set discovery avoids per-entity configuration requests.
- [x] Insufficient evidence is distinguished from confirmed zero delivery.
- [x] `HierarchyService` builds independently evaluated historical trees and
  preserves delivered entities whose historical parent is absent.
- [x] Unit tests and realistic Meta response fixtures cover the historical
  discovery rules and failure paths.

## Delivery Evidence Rule

An Insights row qualifies as historically delivered when at least one of these
normalized metrics is greater than zero:

```text
impressions
spend
reach
```

Only positive indicators are stored in `DeliveryEvidence`. Purchases, ROAS,
current entity status, and creation time do not determine inclusion. The three
evidence metrics are automatically combined with metrics explicitly requested
by the consumer.

If Meta returns an entity row but omits all three required evidence metrics,
the result is `INSUFFICIENT_EVIDENCE`. `HistoricalService` raises
`UnexpectedResponseException` rather than presenting an uncertain response as
confirmed absence. Explicit zero values produce `NOT_DELIVERED` and are omitted
from the delivered result collection.

## Public Usage

Basic Campaign discovery:

```php
use MetaMetrics\Client\MetaClient;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Historical\HistoricalService;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsService;

$config = new MetaConfig($accessToken, $adAccountId, $apiVersion);
$insights = new InsightsService(new MetaClient($config), $config);
$historical = new HistoricalService($insights);

$results = $historical->campaigns(
    new DateRange('2026-09-01', '2026-09-24'),
);
```

Use `HistoricalQuery` for metrics, filtering, parent scope, or daily results:

```php
use MetaMetrics\Historical\HistoricalLevel;
use MetaMetrics\Historical\HistoricalQuery;
use MetaMetrics\Insights\InsightsFilterField;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Query\Filter;
use MetaMetrics\Query\FilterOperator;

$query = new HistoricalQuery(
    level: HistoricalLevel::AD_SET,
    dateRange: new DateRange('2026-09-01', '2026-09-24'),
    parentId: $campaignId,
    metrics: [Metric::PURCHASES, Metric::ROAS],
    daily: true,
    filters: [
        new Filter(
            InsightsFilterField::AD_SET_ID->value,
            FilterOperator::IN,
            [$adSetId],
        ),
    ],
    limit: 100,
);

$results = $historical->get($query);
```

Each result exposes identity and parent IDs, the complete requested period,
the evidence used for inclusion, and its normalized Insights result:

```php
foreach ($results as $result) {
    $result->entityId();
    $result->campaignId();
    $result->adSetId();
    $result->adId();
    $result->requestedSince();
    $result->requestedUntil();
    $result->deliveryEvidence()->indicators();
    $result->insights()->metrics();
}
```

The primary request is:

```text
GET /{api-version}/{scope-id}/insights
```

with `level`, `time_range`, the required metric fields, optional `filtering`,
and `time_increment=1` only in daily mode. Pagination is handled by the shared
Paginator. Historical queries disable Ad Set configuration enrichment because
`optimization_goal` and `promoted_object` are not delivery evidence. Standard
Ad Set Insights queries continue to retrieve those fields by default for CPR
processing. Therefore historical discovery does not issue this per-entity call:

```text
GET /{api-version}/{adset-id}?fields=id,optimization_goal,promoted_object
```

No historical Campaign, Ad Set, or Ad metadata endpoint is queried to inspect
current status or creation time.

Historical hierarchy uses three independent level-wide Insights queries:

```php
use MetaMetrics\Hierarchy\HierarchyService;

$hierarchy = new HierarchyService($historical);
$tree = $hierarchy->historical(
    new DateRange('2026-09-01', '2026-09-24'),
);

$tree->campaigns();
$tree->unattachedAdSets();
$tree->unattachedAds();
```

Only delivered entities become nodes. Ad Sets and Ads are attached when a
delivered parent exists for the same reporting row. Delivered children whose
parent has no delivery evidence remain available through the unattached
collections, preserving parent/child independence without inferring delivery.

Implement the **Historical Data module** for MetaMetrics.

The purpose of this module is to answer questions such as:

```text
Which Campaigns, Ad Sets, and Ads actually had delivery/performance
during a requested historical period?
```

This module must NOT determine historical activity from an entity's current status or creation date.

Its primary evidence source is date-scoped Meta Insights data.

---

# 1. Primary Goal

Support historical discovery for:

```text
Campaigns
Ad Sets
Ads
```

within an explicit reporting period.

Example:

```text
Requested period:
2026-09-01 → 2026-09-24
```

The library should be able to identify entities that had relevant Meta delivery/performance during that period, including entities that:

```text
were created before September
are currently paused
are currently archived
changed status after the requested period
```

provided Meta Insights contain sufficient evidence that they delivered/performed during the requested period.

The conceptual pipeline is:

```text
Historical Query
      ↓
HistoricalService
      ↓
Date-scoped Insights
      ↓
DeliveryDiscovery
      ↓
HistoricalResult
```

Historical discovery should build on the existing Insights infrastructure rather than creating an independent analytics implementation.

---

# 2. Fundamental Semantic Distinction

Keep these concepts separate:

```text
Entity existence
Current entity status
Historical delivery
```

They are NOT equivalent.

For example:

```text
Campaign created:
2026-08-10

Campaign current status:
PAUSED

Requested period:
2026-09-01 → 2026-09-24

Insights during requested period:
Spend > 0
Impressions > 0
```

This Campaign had historical delivery during the requested period even though it is currently paused.

Similarly:

```text
Current status = ACTIVE
```

does NOT prove that the entity delivered during a previous period.

Never implement:

```text
historicallyActive = currentStatus === ACTIVE
```

or equivalent logic.

---

# 3. Historical Delivery Is Not Status History

The initial Historical module is about discovering historical **delivery/performance**, not reconstructing every status transition an entity experienced.

Do not claim that MetaMetrics knows:

```text
Campaign was ACTIVE from 10:00 to 14:00
Campaign was PAUSED from 14:00 to 18:00
```

unless a future implementation obtains a dedicated source of status-history data that supports such claims.

Current scope asks:

```text
Did this entity have relevant delivery/performance
during the requested period?
```

not:

```text
What was its exact historical status at every moment?
```

Use terminology accordingly.

Prefer:

```text
historically delivered
had delivery
had performance
relevant during period
```

over ambiguous claims such as:

```text
was ACTIVE
```

when the evidence is Insights data.

---

# 4. HistoricalService

HistoricalService should orchestrate historical discovery.

Its responsibilities include:

* accepting the requested date range
* selecting the requested entity level
* obtaining appropriate date-scoped Insights
* passing normalized Insights to DeliveryDiscovery
* collecting qualifying entities
* returning HistoricalResult objects
* preserving reporting-period information
* avoiding unnecessary N+1 requests

It must NOT:

* implement HTTP communication
* manually parse raw Meta actions
* duplicate metric formulas
* use current status as historical evidence
* infer delivery from creation date
* contain framework-specific application logic

---

# 5. DeliveryDiscovery

Keep the actual historical relevance rule centralized.

Conceptually:

```text
InsightsDTO
     ↓
DeliveryDiscovery
     ↓
Delivered / Not Delivered
```

Do not scatter historical-delivery checks throughout:

```text
CampaignService
AdSetService
AdService
InsightsService
HierarchyService
```

There should be one authoritative component responsible for interpreting whether normalized Insights constitute sufficient delivery evidence.

---

# 6. Historical Query Model

Use a structured query/value representation where useful.

A historical query conceptually needs:

```text
entity level
date range
optional parent scope
optional filters
optional result detail
```

Examples:

```text
Campaigns
2026-09-01 → 2026-09-24
```

or:

```text
Ad Sets
under Campaign X
2026-09-01 → 2026-09-24
```

or:

```text
Ads
under Ad Set Y
2026-09-01 → 2026-09-24
```

Do not expose a large collection of loosely related primitive arguments if a query object provides a cleaner API.

---

# 7. Supported Historical Levels

Support:

```text
Campaign
Ad Set
Ad
```

Account-level historical Insights may be available through InsightsService, but historical entity discovery primarily concerns entities underneath the Ad Account.

Use controlled enum/value representations rather than arbitrary level strings.

---

# 8. DateRange Is Mandatory

Historical discovery has no meaning without a clearly defined reporting period.

Require:

```text
since
until
```

or a DateRange object representing those values.

Validate the range before making network requests.

Reject:

```text
since > until
```

Do not silently repair invalid ranges.

Reuse the same DateRange semantics as Insights.

Do not create another incompatible historical date implementation.

---

# 9. Date Range Is Inclusive According to Meta Reporting Semantics

The Historical module should use the same reporting-boundary semantics as the Insights module.

Do not independently reinterpret date boundaries.

For example:

```text
2026-09-01 → 2026-09-24
```

must be passed through the established Insights date-range behavior.

Avoid off-by-one-day bugs caused by separately manipulating historical dates.

---

# 10. Account Timezone

Historical reporting periods must remain consistent with the Meta Ad Account's reporting timezone.

Do not assume that:

```text
currency = BDT
```

means:

```text
timezone = Asia/Dhaka
```

For example, an account may legitimately have:

```text
currency: BDT
timezone_name: Europe/London
```

Historical boundaries should follow the same account/reporting semantics used by Insights.

Do not infer timezone from currency, server location, consumer location, or PHP default timezone.

---

# 11. Correct Historical Discovery Strategy

Prefer date-scoped Insights queries at the appropriate Meta level.

Example:

```text
Ad Account
   ↓
Insights
level = campaign
time_range = requested period
```

Conceptually this allows MetaMetrics to discover Campaigns with performance during the requested period without first fetching every Campaign individually.

Similarly, where supported:

```text
Campaign scope
   ↓
Insights
level = adset
```

and:

```text
Ad Set / appropriate scope
   ↓
Insights
level = ad
```

Verify the exact supported combinations against current Meta Marketing API documentation.

---

# 12. Avoid N+1 Historical Queries

Do NOT default to:

```text
Fetch all campaigns

for each campaign:
    fetch historical insights
```

For an account with:

```text
1,000 campaigns
```

that can produce approximately:

```text
1 + 1,000 requests
```

before pagination and other calls are considered.

Instead, prefer Meta's level-based Insights capabilities where they can retrieve multiple entities in one logical query.

The same principle applies to Ad Sets and Ads.

---

# 13. Historical Inclusion Must Not Depend on Creation Date

Never implement:

```text
created_time >= since
AND
created_time <= until
```

as the criterion for historical inclusion.

Example:

```text
Campaign created:
2026-01-01

Requested period:
2026-09-01 → 2026-09-24

Campaign delivered:
2026-09-05 → 2026-09-20
```

The Campaign must be discoverable for September even though it was created eight months earlier.

Creation date describes entity lifecycle metadata.

It does not describe reporting-period delivery.

---

# 14. Entities Created During the Period

An entity created inside the requested period may also qualify.

Example:

```text
Requested period:
2026-09-01 → 2026-09-24

Campaign created:
2026-09-12

Delivery:
2026-09-13 → 2026-09-20
```

It should be included because Insights provide delivery evidence within the requested period.

Again:

```text
creation date
```

alone does not qualify it.

Delivery/performance evidence does.

---

# 15. Current Status Must Not Filter Historical Results

Do not automatically exclude:

```text
PAUSED
ARCHIVED
DELETED-like/non-active states where Meta still exposes historical reporting
```

merely because they are not currently active.

If Meta returns historical Insights establishing delivery within the requested period, preserve the entity.

Likewise, do not automatically include an entity simply because:

```text
status = ACTIVE
```

today.

Current status is metadata, not historical delivery evidence.

---

# 16. Define Delivery Evidence Explicitly

DeliveryDiscovery needs an explicit, documented rule.

Do not use vague logic such as:

```php
return !empty($insights);
```

without defining what an Insights row means.

Potential evidence may include:

```text
impressions > 0
spend > 0
reach > 0
other appropriate delivery/performance indicators
```

However, do not arbitrarily choose or combine these metrics.

Before finalizing the rule:

1. Review the project's intended meaning of "had delivery/performance".
2. Verify Meta Insights behavior.
3. Define the criterion centrally.
4. Document the criterion.
5. Test edge cases.

The criterion must be replaceable without redesigning HistoricalService.

---

# 17. Do Not Use Conversion Metrics as Primary Delivery Evidence

Metrics such as:

```text
Purchases
Purchase Value
ROAS
```

are outcome metrics, not reliable universal evidence of whether an ad delivered.

An ad can legitimately have:

```text
Impressions > 0
Spend > 0
Purchases = 0
```

and still clearly have delivered.

Do not require conversions for historical inclusion.

---

# 18. Zero-Conversion Entities

This case must work correctly:

```text
Spend:       500
Impressions: 10,000
Clicks:      100
Purchases:   0
```

The absence of purchases must not cause the entity to disappear from historical delivery results.

Historical delivery and conversion success are separate concepts.

---

# 19. Organic/Non-Ad Activity Is Outside Scope

HistoricalService only reasons from Meta advertising data exposed through the Marketing API.

Do not attempt to infer:

```text
organic Facebook activity
organic Instagram activity
ecommerce sales outside Meta attribution
website activity unrelated to ads
```

The module answers historical Meta Ads questions only.

---

# 20. HistoricalResult

Return a stable normalized result.

Conceptually:

```text
entityId
entityName
level

campaignId
adSetId
adId

requestedSince
requestedUntil

deliveryEvidence

insights
```

The exact DTO shape may follow existing project conventions.

The result should make it clear:

```text
which entity
which level
which reporting period
what evidence was found
```

Do not expose raw Meta response structures as the default historical result.

---

# 21. Preserve Entity Relationships

For lower-level results, preserve available parent IDs.

Example Ad Set:

```text
campaignId
adSetId
```

Example Ad:

```text
campaignId
adSetId
adId
```

Do not issue extra parent-detail requests merely to obtain information already returned by the Insights query.

IDs must remain strings.

---

# 22. Historical Result vs Insights Result

Do not make HistoricalResult another complete independent analytics model.

InsightsDTO already represents performance.

HistoricalResult should primarily add historical discovery semantics around that data.

Conceptually:

```text
HistoricalResult
    ├── entity identity
    ├── requested period
    ├── delivery determination/evidence
    └── InsightsDTO
```

Avoid copying every analytics field into multiple unrelated representations unless there is a strong design reason.

---

# 23. Aggregate Historical Discovery

For:

```text
2026-09-01 → 2026-09-24
```

the simplest historical discovery mode should answer:

```text
Which entities had delivery/performance at any point
during this complete period?
```

It does not necessarily need to identify the exact delivery day.

Use aggregate period Insights where sufficient.

This can be more efficient than requesting daily breakdowns.

---

# 24. Daily Historical Discovery

Support daily data when the consumer explicitly needs it.

Example use case:

```text
Which campaigns delivered on each day
between September 1 and September 24?
```

This requires daily Insights semantics.

Do not always request daily breakdowns for simple historical inclusion queries.

Choose the least expensive data granularity that satisfies the query.

---

# 25. Do Not Infer Continuous Delivery

Suppose aggregate Insights show:

```text
Campaign X
2026-09-01 → 2026-09-24
Impressions > 0
```

This proves delivery occurred somewhere within that interval according to the defined evidence.

It does NOT prove:

```text
Campaign X delivered every day
from September 1 through September 24.
```

Do not infer continuous activity from aggregate data.

If exact delivery days are required, use daily breakdown data.

---

# 26. Daily Gaps

For daily historical results, distinguish:

```text
day with delivery
```

from:

```text
day without a returned row
```

carefully.

Do not automatically fabricate zero-valued rows for missing dates unless the API contract and MetaMetrics normalization explicitly define that behavior.

If zero-filling is later supported, make it an explicit transformation rather than silently altering Meta's response.

---

# 27. Empty Historical Results Are Valid

A successful Meta response may be:

```json
{
  "data": []
}
```

This is a valid result.

For example:

```text
Requested period:
2026-09-01 → 2026-09-24

Matching entities:
none
```

HistoricalService should return an empty result collection.

Do NOT throw:

```text
ResourceNotFoundException
UnexpectedResponseException
```

just because no entity delivered during the period.

Distinguish:

```text
successful query with no matching history
```

from:

```text
API request failure
```

---

# 28. Entity Not Found vs No Historical Delivery

These are different states.

Example A:

```text
Campaign ID does not exist / is inaccessible
```

This may justify a resource/access error.

Example B:

```text
Campaign exists
but has no Insights during requested period
```

This is normally:

```text
valid empty historical result
```

Do not collapse both into "not found".

---

# 29. Current Metadata Enrichment

Historical discovery may optionally enrich results with current entity metadata such as:

```text
name
current status
effective status
creation time
```

when useful.

But enrichment must remain separate from historical evidence.

For example:

```text
Historical delivery: true
Current status: PAUSED
```

is perfectly valid.

Do not allow current metadata retrieval failure to corrupt already valid historical Insights unless the metadata is required by the public contract.

Avoid unnecessary enrichment requests by default.

---

# 30. Historical Hierarchy

Support building historical hierarchy through the existing hierarchy architecture where required.

Conceptually:

```text
Campaign A
 ├── Ad Set A1
 │    ├── Ad A1-1
 │    └── Ad A1-2
 └── Ad Set A2
      └── Ad A2-1
```

But when historical filtering is applied, include entities based on requested-period historical evidence rather than current status.

Example:

```text
Campaign A
    historical delivery = yes

Ad Set A1
    historical delivery = yes

Ad A1-1
    historical delivery = no
```

The historical hierarchy layer should preserve these distinctions according to its defined inclusion strategy.

Do not make HistoricalService itself responsible for recursively constructing all hierarchy trees.

---

# 31. Parent and Child Delivery Are Separate Facts

Do not automatically assume:

```text
Campaign had delivery
→ every Ad Set had delivery
```

or:

```text
Ad Set had delivery
→ every Ad had delivery
```

Historical relevance should be evaluated at the requested entity level.

A Campaign may contain many Ad Sets while only some delivered during the requested period.

Likewise, an Ad Set may contain many Ads while only some delivered.

---

# 32. Filtering

Historical queries may support filters such as:

```text
specific Campaign
specific Ad Set
specific Ad
additional Meta-supported filters
```

Use the shared filtering/query infrastructure.

Do not manually concatenate unvalidated filter input into Meta API parameters.

---

# 33. Metric Requirements for Discovery

Historical discovery should request only the metrics needed to evaluate the configured delivery rule plus any metrics explicitly requested by the consumer.

Example concept:

```text
DeliveryDiscovery requires:
    impressions
    spend

Consumer additionally requests:
    purchases
    ROAS
```

The effective Insights query should resolve the union of those dependencies.

Do not request the complete metric catalog automatically for every historical discovery query.

---

# 34. Pagination

Historical discovery must work across all Insights pages.

Example:

```text
Page 1 → 25 Campaigns
Page 2 → 25 Campaigns
Page 3 → 12 Campaigns
```

DeliveryDiscovery must receive all relevant rows.

Do not classify historical results using only the first page.

Reuse the shared Paginator.

---

# 35. Large Account Performance

Design for accounts containing:

```text
hundreds/thousands of Campaigns
thousands of Ad Sets
many thousands of Ads
```

Avoid:

* N+1 Insights requests
* unnecessary entity-detail requests
* loading unrelated metrics
* repeated normalization
* repeated identical API requests
* recursive uncontrolled fetching

Where possible, process paginated data incrementally or through memory-conscious collections.

Do not assume every account is small.

---

# 36. Rate Limit Awareness

Historical queries can be expensive.

Use the shared rate-limit handling.

HistoricalService must not implement uncontrolled retry loops.

If Meta returns a rate-limit condition:

```text
MetaClient
    ↓
RateLimitException
```

should propagate through the established architecture.

Historical discovery should remain retry-friendly without hammering Meta's API.

---

# 37. Caching Compatibility

Historical data is a strong candidate for optional caching because past reporting periods are generally less volatile than current-period data.

However:

* caching must remain optional
* HistoricalService must not require Redis/database/file storage
* use the shared cache abstraction if caching is enabled
* do not tightly couple historical logic to a framework cache

A future cache key may conceptually depend on:

```text
Ad Account
Entity Level
Scope
Date Range
Filters
Metrics
Breakdown
API Version
```

Do not introduce caching that can return results for the wrong reporting context.

---

# 38. Raw Response Access

HistoricalService should return normalized results.

Do not expose:

```text
paging cursors
raw actions
raw action_values
raw Graph API envelopes
```

as the default historical API.

Advanced/raw access should remain part of the shared debugging/client mechanism.

---

# 39. Error Handling

Use the existing exception hierarchy.

Possible errors include:

```text
AuthenticationException
PermissionException
RateLimitException
NetworkException
InvalidInputException
ResourceNotFoundException
UnsupportedMetricException
UnexpectedResponseException
```

Local validation errors should occur before network requests.

Meta API failures should be mapped through the shared client/error handling layer.

HistoricalService should not reinterpret every exception as:

```text
No historical data
```

An API failure and an empty historical result are fundamentally different.

---

# 40. Partial Failure

Do not silently return incomplete historical results if pagination or a required downstream request fails halfway through.

Example:

```text
Page 1 successful
Page 2 successful
Page 3 network failure
```

Do not return pages 1–2 as if the historical query completed successfully.

Either:

* propagate the appropriate failure, or
* use an explicitly designed partial-result contract if such a feature is intentionally introduced later.

Default behavior should favor correctness.

---

# 41. Deterministic Results

Given:

```text
same account
same date range
same level
same filters
same API-visible data
```

Historical discovery should produce semantically consistent results.

Avoid dependence on:

```text
current system time
local machine timezone
random ordering
current entity status
```

unless those values are explicitly part of the query.

---

# 42. Testing Strategy

Use mocked/fake Meta responses.

Do not require a live advertising account.

Add tests for at least the following scenarios.

## Campaign Created Before Range

```text
Created:
2026-08-01

Requested:
2026-09-01 → 2026-09-24

Insights:
delivery exists
```

Expected:

```text
Campaign included
```

---

## Campaign Created Inside Range

```text
Created:
2026-09-10

Insights:
delivery exists
```

Expected:

```text
Campaign included
```

---

## Campaign Created Inside Range but No Delivery

```text
Created:
2026-09-10

Insights:
none
```

Expected:

```text
Campaign not included as historically delivered
```

Creation alone is insufficient.

---

## Currently Paused but Historically Delivered

```text
Current status:
PAUSED

Requested-period Insights:
delivery exists
```

Expected:

```text
included
```

---

## Currently Active but No Historical Delivery

```text
Current status:
ACTIVE

Requested historical period:
no delivery
```

Expected:

```text
not included as historically delivered
```

---

## Zero Purchases but Delivery Exists

```text
Spend > 0
Impressions > 0
Purchases = 0
```

Expected:

```text
included
```

---

## Empty Meta Response

```json
{
  "data": []
}
```

Expected:

```text
empty successful HistoricalResult collection
```

---

## Multiple Campaigns

Some Campaigns have delivery evidence and others do not.

Ensure only qualifying entities are returned.

---

## Ad Set Historical Discovery

Verify parent Campaign ID is preserved.

---

## Ad Historical Discovery

Verify:

```text
campaignId
adSetId
adId
```

are preserved.

---

## Parent/Child Independence

Campaign delivery must not automatically mark every child Ad Set or Ad as delivered.

---

## Aggregate Period

Verify aggregate Insights can establish:

```text
delivery occurred somewhere in requested period
```

without claiming exact delivery days.

---

## Daily Period

Verify daily results identify delivery per day correctly.

---

## Daily Gap

Ensure missing daily rows are not silently invented unless explicit zero-filling behavior exists.

---

## Pagination

Verify entities across multiple pages are all evaluated.

---

## Invalid Date Range

Verify local failure before any Meta request.

---

## Authentication Failure

Verify proper propagation.

---

## Permission Failure

Verify proper propagation.

---

## Rate Limit

Verify proper propagation.

---

## Network Failure Mid-Pagination

Ensure incomplete data is not returned as complete historical data.

---

## Credential Safety

Ensure access tokens never appear in errors/logs.

---

# 43. Fixtures

Create realistic historical fixtures covering:

```text
historically delivered + currently active

historically delivered + currently paused

currently active + no historical delivery

created before range + delivered in range

created during range + delivered

created during range + no delivery

delivery + zero conversions

multiple entity rows

empty data

daily rows

paginated rows
```

Fixtures should resemble current Meta API response structures.

Do not fabricate unsupported Meta fields merely to simplify tests.

---

# 44. Official Meta API Verification

Before implementing exact request behavior, verify current official Meta Marketing API documentation for:

* Insights endpoint behavior
* supported levels
* entity scopes
* time_range
* time_increment
* filters
* pagination
* fields
* historical reporting limitations
* archived/deleted entity reporting behavior
* retention/availability constraints
* attribution behavior
* API version differences

Do not make undocumented guarantees about how far historical data is available.

Do not assume historical Insights availability is unlimited.

Keep Meta-specific behavior isolated so API-version changes can be accommodated without redesigning the public Historical API.

---

# 45. Do Not Implement Historical Status Reconstruction

Do not attempt to create a timeline such as:

```text
09-01 ACTIVE
09-05 PAUSED
09-07 ACTIVE
09-15 PAUSED
```

from Insights.

Insights performance data does not automatically provide a reliable status-transition history.

If status-history reconstruction becomes a future requirement, implement it as a separate capability backed by an appropriate Meta data source.

---

# 46. Do Not Guess Missing History

If Meta does not return enough information to determine historical delivery, do not fabricate a conclusion.

Keep the distinction between:

```text
confirmed delivery
confirmed absence where semantics allow
unknown / insufficient evidence
```

where necessary.

Do not transform uncertainty into false precision.

---

# 47. Public API Goal

The consumer experience should conceptually allow:

```php
$range = DateRange::between(
    '2026-09-01',
    '2026-09-24'
);

$results = $metaMetrics
    ->historical()
    ->campaigns($range);
```

and potentially:

```php
$results = $metaMetrics
    ->historical()
    ->adSets($range);
```

or:

```php
$results = $metaMetrics
    ->historical()
    ->ads($range);
```

Exact public method names should follow the architecture and coding conventions already established in the project.

A more generic query-based API is also acceptable if it produces a cleaner design.

The consumer should NOT need to:

```text
fetch all Campaigns
inspect current status
compare creation dates
call Insights once per Campaign
parse Meta pagination
parse raw Insights
decide delivery manually
```

MetaMetrics should own that complexity.

---

# 48. Example Expected Behavior

Assume today is September 24.

The account contains:

```text
Campaign A
Created: August 10
Current Status: PAUSED
September Spend: 5,000
September Impressions: 50,000

Campaign B
Created: September 5
Current Status: ACTIVE
September Spend: 2,000
September Impressions: 20,000

Campaign C
Created: July 1
Current Status: ACTIVE
September delivery: none

Campaign D
Created: September 15
Current Status: PAUSED
September Spend: 700
September Impressions: 8,000
```

Request:

```text
Historical Campaigns
2026-09-01 → 2026-09-24
```

Based on the configured delivery-evidence rule, the result should conceptually contain:

```text
Campaign A
Campaign B
Campaign D
```

Campaign A must not be excluded because it was created before September or is currently paused.

Campaign C must not be included merely because it is currently active.

Campaign D can qualify despite currently being paused because requested-period delivery exists.

The decision is driven by requested-period Insights evidence.

---

# 49. Key Invariant

Preserve this invariant throughout the implementation:

```text
Historical relevance
≠
Current status
≠
Creation date
```

Instead:

```text
Requested Date Range
        +
Date-scoped Insights
        +
Explicit Delivery Evidence Rule
        ↓
Historical Relevance
```

This distinction is one of the central design requirements of MetaMetrics.

---

# 50. Final Responsibility Map

Maintain this architecture:

```text
MetaClient
    → communicate with Meta

InsightsService
    → retrieve date-scoped performance data

InsightsParser
    → interpret Meta response structures

InsightsNormalizer
    → produce normalized analytics

DeliveryDiscovery
    → evaluate delivery evidence

HistoricalService
    → orchestrate historical entity discovery

HistoricalResult
    → represent historical discovery result

CampaignService
    → Campaign metadata/retrieval

AdSetService
    → Ad Set metadata/retrieval

AdService
    → Ad metadata/retrieval

HierarchyService
    → construct entity relationships when requested
```

HistoricalService must build on InsightsService rather than duplicating it.

The final conceptual flow is:

```text
Consumer Historical Query
          ↓
Validate Date Range / Level / Scope
          ↓
Resolve Metrics Required for Delivery Evidence
          ↓
InsightsService
          ↓
Meta Marketing API
          ↓
Paginated Date-scoped Insights
          ↓
Normalized InsightsDTOs
          ↓
DeliveryDiscovery
          ↓
Qualifying Historical Entities
          ↓
HistoricalResult Collection
```

Optimize for semantic correctness first.

The module's core promise is not:

```text
"Which entities are active now?"
```

It is:

```text
"Which entities have evidence of Meta Ads delivery/performance
during this explicitly requested reporting period?"
```
