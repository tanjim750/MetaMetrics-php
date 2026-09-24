# MetaMetrics — Insights Implementation Instructions

Implement the **Insights module** for MetaMetrics.

The Insights module is responsible for retrieving, parsing, normalizing, and calculating Meta Ads performance data for a specific reporting period.

This is a library component. Do not add HTTP routes, controllers, UI, database persistence, framework-specific code, or application-specific business logic.

The consumer should interact with normalized MetaMetrics analytics rather than Meta's raw Insights response format.

---

## 1. Primary Goal

Implement a reusable Insights pipeline:

```text
Consumer
   ↓
InsightsQuery
   ↓
InsightsService
   ↓
MetaClient
   ↓
Meta Marketing API
   ↓
InsightsParser
   ↓
InsightsNormalizer
   ↓
MetricCalculator
   ↓
InsightsDTO
```

The implementation must support Insights at these levels:

```text
Account
Campaign
Ad Set
Ad
```

and support both:

```text
Aggregate performance
Daily performance
```

for an explicitly requested reporting period.

---

# 2. Architectural Boundary

The responsibilities must remain separated.

### InsightsService

Responsible for:

* orchestrating Insights requests
* resolving requested fields
* resolving metric dependencies
* building API parameters
* invoking MetaClient
* invoking pagination where necessary
* passing raw rows through the parsing/normalization pipeline
* returning normalized Insights results

It must NOT contain large amounts of action parsing, mathematical calculation, date manipulation, or HTTP transport logic.

### InsightsQuery

Represents what analytics the consumer wants.

It should describe concepts such as:

```text
scope/entity
level
date range
metrics
breakdown mode
filters
```

### DateRange

Represents an explicit reporting period.

### DatePreset

Provides convenient date-range creation where appropriate.

### InsightsParser

Interprets the structure of a raw Meta Insights row.

### ActionParser

Extracts action counts such as purchase-related conversions.

### ActionValueParser

Extracts monetary conversion values.

### InsightsNormalizer

Transforms parsed Meta data into stable MetaMetrics data.

### MetricRegistry

Defines supported metrics and their dependencies.

### MetricCalculator

Calculates derived metrics.

### MetaClient

Owns HTTP/API communication.

### Paginator

Owns cursor pagination.

Do not collapse these responsibilities into one large service.

---

# 3. Supported Insight Levels

Provide a controlled representation for:

```text
ACCOUNT
CAMPAIGN
AD_SET
AD
```

Prefer an enum if compatible with the project's PHP version.

Do not allow arbitrary level strings to propagate throughout the implementation.

Map the internal representation to Meta's currently supported Insights `level` values only at the API/request boundary.

Before implementing exact Meta values, verify them against the current official Meta Marketing API documentation.

---

# 4. InsightsQuery

Create a clean immutable query representation.

Conceptually it should support:

```php
level
entityId / scope
dateRange
metrics
breakdown
filters
```

Avoid APIs such as:

```php
getInsights(
    $id,
    $level,
    $since,
    $until,
    $daily,
    $fields,
    $filters,
    ...
);
```

with an ever-growing argument list.

Prefer:

```php
$query = new InsightsQuery(...);

$results = $insightsService->get($query);
```

Exact public method names may follow the existing project conventions.

Validate the query before performing network requests.

---

# 5. Scope vs Level

Do not confuse the API resource being queried with the Insights aggregation level.

For example, a request may conceptually target:

```text
Ad Account
```

while requesting:

```text
Campaign-level Insights
```

The implementation should preserve this distinction where Meta supports it.

This is important for queries such as:

```text
Give me all campaign performance
for this Ad Account
between September 1 and September 24.
```

The consumer should not need to fetch every Campaign and then issue one Insights request per Campaign.

Prefer efficient Meta-supported account-scoped Insights queries with the appropriate level.

This avoids unnecessary N+1 API requests.

---

# 6. DateRange

Use a dedicated immutable DateRange value object.

It must contain:

```text
since
until
```

Use a predictable date representation internally.

Validate:

```text
since <= until
```

Reject:

* malformed dates
* impossible dates
* missing boundaries where required
* `since > until`

Do not silently correct or swap invalid ranges.

---

# 7. Date Range Is Authoritative

This is a critical requirement.

Suppose:

```text
Campaign created:
2026-08-10

Requested Insights:
2026-09-01 → 2026-09-24
```

Only statistics belonging to:

```text
2026-09-01 → 2026-09-24
```

must be returned.

Do not include August statistics simply because the Campaign existed in August.

Entity creation date must never expand the requested reporting period.

Likewise, an entity created before the requested range may still have valid Insights inside the requested range.

---

# 8. Account Timezone

Reporting periods must respect Meta Ad Account reporting semantics.

Do not assume:

```text
PHP server timezone
```

or:

```text
consumer application's timezone
```

is automatically the correct reporting timezone.

For example, an Ad Account may return:

```text
currency: BDT
timezone_name: Europe/London
```

Those are independent account settings.

For concepts such as:

```text
Today
Yesterday
Current Month
Previous Month
Daily breakdown
```

the Ad Account timezone must be considered where Meta reporting boundaries depend on it.

Do not infer timezone from currency.

Do not infer timezone from the consumer's geographical location.

---

# 9. Date Presets

Support normalized convenience concepts such as:

```text
Today
Yesterday
Current Month
Previous Month
```

and custom date ranges.

Centralize preset resolution.

Do not duplicate logic such as "previous month start/end" throughout different services.

If Meta's own `date_preset` semantics are used, verify currently supported values against official documentation.

For deterministic library behavior, custom `time_range` generation may be preferable where appropriate.

---

# 10. Aggregate Insights

Support aggregate statistics for the complete requested period.

Example:

```text
2026-09-01 → 2026-09-24

Spend:          10,000
Impressions:    100,000
Clicks:         3,000
Purchases:      120
Purchase Value: 30,000
```

This represents one requested reporting interval.

Prefer Meta's native aggregate Insights capability.

Do not fetch daily rows and manually aggregate them unless there is a justified requirement.

---

# 11. Daily Insights

Support daily performance breakdown.

Conceptually:

```text
2026-09-01
2026-09-02
2026-09-03
...
2026-09-24
```

Use Meta's supported time increment functionality where possible.

Do NOT implement daily reporting as:

```text
24 days
=
24 separate HTTP requests
```

when Meta can return daily rows from a single logical Insights query.

Daily results must preserve their own:

```text
dateStart
dateStop
```

values.

---

# 12. Core Supported Metrics

Implement support for these normalized MetaMetrics metrics:

```text
Spend

Meta-attributed Purchases
Cost Per Purchase

Purchase Conversion Value
ROAS

Impressions
Reach

Clicks
Link Clicks

CPC
CTR
CPM
```

The exact Meta API fields required to obtain these metrics must be verified against the current official Meta Marketing API documentation.

Do not invent field names based on assumptions or outdated examples.

---

# 13. Foundational vs Derived Metrics

Separate foundational metrics from derived metrics.

Conceptually:

```text
Foundational data
    ↓
Derived metrics
```

Examples of foundational data:

```text
Spend
Purchases
Purchase Conversion Value
Impressions
Reach
Clicks
Link Clicks
```

Derived metrics:

```text
Cost Per Purchase
ROAS
CPC
CTR
CPM
```

This distinction should be represented in MetricRegistry.

---

# 14. MetricRegistry

MetricRegistry should be the central source of truth for supported metrics.

It should be able to answer questions such as:

```text
Is this metric supported?

What Meta fields are required?

Does this metric depend on another metric?

Is this metric directly parsed or derived?
```

Example conceptual dependency:

```text
ROAS
 ├── Spend
 └── Purchase Conversion Value
```

Similarly:

```text
Cost Per Purchase
 ├── Spend
 └── Purchases

CPC
 ├── Spend
 └── Clicks

CTR
 ├── Clicks
 └── Impressions

CPM
 ├── Spend
 └── Impressions
```

---

# 15. Automatic Metric Dependency Resolution

A consumer may request:

```text
ROAS
```

without explicitly requesting:

```text
Spend
Purchase Conversion Value
```

MetaMetrics should automatically resolve the required dependencies internally.

Likewise:

```text
CTR
```

requires:

```text
Clicks
Impressions
```

The consumer should request business-level metrics, not manually understand internal calculation dependencies.

Avoid requesting unrelated Meta fields unnecessarily.

---

# 16. Derived Metric Formulas

Implement derived calculations centrally.

## Cost Per Purchase

```text
Spend / Purchases
```

## ROAS

```text
Purchase Conversion Value / Spend
```

## CPC

```text
Spend / Clicks
```

## CTR

```text
(Clicks / Impressions) × 100
```

## CPM

```text
(Spend / Impressions) × 1000
```

Do not duplicate these formulas in CampaignService, AdSetService, AdService, HistoricalService, or normalizers.

---

# 17. Raw Meta Metrics vs MetaMetrics Derived Metrics

Meta may expose fields that resemble metrics MetaMetrics can calculate.

Do not unpredictably mix:

```text
Meta-returned CPC
```

with:

```text
MetaMetrics-calculated CPC
```

under the same semantic contract.

Establish a consistent rule.

Prefer deriving deterministic ratios from normalized foundational metrics when appropriate.

If raw Meta versions must also be preserved, distinguish their source explicitly.

Never silently overwrite one representation with another.

---

# 18. ActionParser

Meta conversion counts may be represented through an `actions` structure.

Do not expose that raw structure to normal consumers.

Conceptually:

```json
{
  "actions": [
    {
      "action_type": "...",
      "value": "..."
    }
  ]
}
```

should become normalized metrics such as:

```text
purchases
linkClicks
...
```

where appropriate.

Never depend on array position.

Wrong:

```php
$purchases = $actions[3]['value'];
```

Correct conceptual behavior:

```text
Find the supported purchase action type
→ extract its value
→ normalize it
```

---

# 19. Purchase Action Semantics

Be especially careful with purchase extraction.

Meta may expose multiple purchase-related action types depending on attribution, event source, API version, and reporting configuration.

Do NOT implement logic such as:

```text
sum every action_type containing "purchase"
```

because this may double-count or mix different semantics.

Before implementing exact purchase mapping:

1. Check current official Meta Marketing API documentation.
2. Determine the appropriate action type(s) for the project's definition of **Meta-attributed Purchases**.
3. Keep the mapping centralized.
4. Make the mapping maintainable as Meta evolves.

The rest of the library must not need to know raw action type names.

---

# 20. ActionValueParser

Purchase count and purchase monetary value are different concepts.

Conceptually:

```text
actions
    → event count

action_values
    → event monetary value
```

Use a dedicated parsing responsibility for action values.

Do not assume the exact same extraction behavior can always be used for both structures.

Example normalized output:

```text
purchases: 10
purchaseValue: 25000
```

Exact current Meta action-value representation must be verified before implementation.

---

# 21. Meta Attribution Boundary

The normalized metric:

```text
purchases
```

means:

```text
Meta-attributed Purchases
```

according to the Insights data returned by Meta.

It does NOT mean:

```text
all ecommerce orders
```

and it does NOT independently establish the consumer application's own attribution truth.

MetaMetrics must not attempt to reconcile Meta purchase attribution with:

* ecommerce database orders
* payment records
* fbp/fbc
* UTM attribution
* organic orders
* consumer-specific attribution models

Those belong to the consuming application.

---

# 22. InsightsParser

InsightsParser should interpret a raw Meta Insights row.

Its job includes extracting raw concepts such as:

```text
entity identity
entity name
parent identifiers
date_start
date_stop
foundational metrics
actions
action values
```

It should delegate specialized action interpretation to:

```text
ActionParser
ActionValueParser
```

Do not perform HTTP requests from parsers.

Do not perform unrelated business calculations from parsers.

---

# 23. InsightsNormalizer

The normalizer should transform parsed Meta data into the stable MetaMetrics representation.

It should handle:

* optional fields
* missing metrics
* numeric strings
* IDs
* dates
* hierarchy identifiers
* normalized metric naming

Do not leak Meta's raw response shape into the public DTO contract.

---

# 24. InsightsDTO

Use an immutable DTO/value representation.

Conceptually, an Insights result may expose:

```text
entityId
entityName
level

campaignId
adSetId
adId

dateStart
dateStop

spend

purchases
costPerPurchase

purchaseValue
roas

impressions
reach

clicks
linkClicks

cpc
ctr
cpm
```

Only hierarchy identifiers relevant to the current level need values.

Example:

```text
Campaign-level row

campaignId = ...
adSetId    = null
adId       = null
```

For Ad-level data:

```text
campaignId = ...
adSetId    = ...
adId       = ...
```

when Meta provides those identifiers.

---

# 25. Preserve IDs as Strings

Never cast Meta entity IDs to PHP integers.

Use:

```php
string
```

for:

```text
Ad Account ID
Campaign ID
Ad Set ID
Ad ID
```

even when the ID contains only digits.

---

# 26. Numeric Normalization

Meta may return analytics numbers as strings.

Examples:

```json
{
  "spend": "1234.50",
  "impressions": "10000",
  "clicks": "245"
}
```

Normalize them consistently.

Counts should have an appropriate numeric representation.

Monetary/ratio values should use a consistent strategy appropriate to the project's PHP requirements.

Avoid arbitrary display rounding inside the data layer.

Presentation formatting belongs to the consumer.

---

# 27. Missing Metric vs Zero Metric

Do not blindly convert every missing metric into:

```text
0
```

because these states can mean different things:

```text
explicit zero
```

versus:

```text
not returned / unavailable
```

Preserve that distinction where possible.

This is especially important for:

```text
Purchases
Purchase Value
ROAS
Cost Per Purchase
```

---

# 28. Safe Division

Never allow:

```text
division by zero
INF
NaN
PHP warnings
```

from derived metrics.

Examples:

```text
Purchases = 0
→ Cost Per Purchase unavailable

Spend = 0
→ ROAS unavailable

Clicks = 0
→ CPC unavailable

Impressions = 0
→ CTR/CPM unavailable
```

Prefer:

```php
null
```

for mathematically undefined metrics unless the project already establishes another convention.

Do not report undefined ratios as `0.0` merely to avoid nulls.

---

# 29. Currency

Insights monetary values belong to the Ad Account's configured currency.

For example, an account may use:

```text
BDT
```

Do not hardcode any currency.

Do not convert currencies inside Insights.

MetaMetrics should report values in the account's reporting currency unless a future explicit currency-conversion feature is introduced.

Currency conversion is outside the current scope.

---

# 30. Empty Insights Are Valid

This requirement is important.

Meta can successfully return:

```json
{
  "data": []
}
```

with HTTP:

```text
200 OK
```

This is NOT an error.

It may simply mean:

```text
No delivery/performance exists for the requested query.
```

The library should return an empty normalized collection/result.

Do NOT throw:

```text
ResourceNotFoundException
UnexpectedResponseException
```

merely because `data` is empty.

Distinguish:

```text
HTTP/API failure
```

from:

```text
successful query with zero Insight rows
```

---

# 31. Historical Delivery Relationship

Insights provide the factual basis for historical delivery discovery.

Example:

```text
Today:
Campaign status = PAUSED

Requested period:
2026-09-01 → 2026-09-24

Insights:
Spend > 0
Impressions > 0
```

The Campaign clearly had performance in the requested period despite currently being paused.

Therefore:

```text
Current status != historical delivery
```

Likewise:

```text
Current status = ACTIVE
```

does not prove that the Campaign delivered during a historical period.

InsightsService should provide date-scoped facts.

HistoricalService should determine historical relevance.

Do not merge both responsibilities.

---

# 32. Do Not Use Creation Date as Delivery Evidence

A Campaign may be created on:

```text
2026-09-05
```

but begin delivering on:

```text
2026-09-10
```

Creation does not prove delivery.

Likewise, a Campaign created before the requested range may still deliver during the requested range.

Historical analysis must rely on appropriate Insights evidence, not entity creation timestamps.

---

# 33. Delivery Evidence Must Remain Explicit

Do not hardcode inside InsightsService:

```text
spend > 0 means delivered
```

as the universal historical rule.

Potential evidence can include:

```text
spend
impressions
reach
other delivery-related data
```

The Historical module should define the exact historical delivery criterion.

Insights only supplies normalized performance evidence.

---

# 34. Efficient Hierarchical Analytics

Support efficient queries such as:

```text
Ad Account
    ↓
Campaign-level Insights
```

and:

```text
Campaign
    ↓
Ad Set-level Insights
```

where Meta supports them.

Avoid designs that automatically do:

```text
fetch 100 campaigns

for each campaign:
    request Insights
```

when a single level-based Insights query can retrieve the required data.

The library should be designed for accounts containing large numbers of entities.

---

# 35. Pagination

Insights responses may contain pagination.

Use the shared paginator.

The Insights layer must correctly handle:

```text
page 1
page 2
page 3
...
```

without:

* duplicate rows
* missing rows
* recursive uncontrolled requests
* leaking cursors to normal consumers

Do not build a second independent pagination implementation specifically for Insights.

---

# 36. Selected Metrics

Allow consumers to request selected metrics.

Conceptually:

```php
metrics: [
    SPEND,
    PURCHASES,
    ROAS
]
```

The internal dependency resolver may turn that into the Meta fields required for:

```text
Spend
Purchases
Purchase Value
```

Do not necessarily request every metric supported by MetaMetrics for every request.

This reduces payload size and unnecessary processing.

---

# 37. Filtering

Integrate with the project's shared query/filter architecture.

Support Insights filters where appropriate for:

```text
Campaign
Ad Set
Ad
Status
Meta-supported filtering
```

Do not concatenate raw consumer strings directly into Meta filtering JSON.

Validate and serialize filters through the shared query layer.

Only support filters that Meta currently supports for the relevant endpoint/level.

---

# 38. Raw Response Access

Normalized results are the default public behavior.

Do not make consumers parse:

```text
actions
action_values
paging
Meta field names
```

for normal analytics usage.

If the library supports raw responses for debugging or advanced integrations, keep that capability explicit and separate from the normalized DTO.

Do not embed the entire raw Meta response inside every DTO by default.

---

# 39. Error Handling

Use the existing exception hierarchy.

Map failures appropriately:

```text
Invalid/expired token
    → AuthenticationException

Missing permission
    → PermissionException

Rate limit
    → RateLimitException

Network failure
    → NetworkException

Invalid local query
    → InvalidInputException

Unsupported metric
    → UnsupportedMetricException

Invalid/inaccessible resource
    → ResourceNotFoundException where appropriate

Malformed/unexpected Meta response
    → UnexpectedResponseException
```

Do not convert every Meta failure into one generic exception.

Preserve safe diagnostic metadata where useful.

Never include access tokens in exception messages.

---

# 40. Rate Limits

Insights queries can be expensive.

Respect the shared rate-limit architecture.

Do not automatically perform uncontrolled retries.

The Insights module should:

* recognize mapped rate-limit exceptions
* remain retry-friendly
* avoid unnecessary N+1 requests
* minimize fields where practical
* use pagination efficiently

Retry policy belongs to the appropriate shared client/retry layer, not random loops inside InsightsService.

---

# 41. Security

Never expose the access token through:

```text
DTOs
exceptions
logs
debug output
query dumps
URLs shown to users
```

If MetaClient sends the token as a query parameter because the API requires/supports that mechanism, sanitize the URL before logging it.

Insights tests must not contain real credentials.

---

# 42. Testing Strategy

Use mocked/fake Meta responses.

Do not depend on a live Ad Account for automated tests.

Create realistic fixtures for the important response shapes.

At minimum test the following.

## Empty Account

Input Meta response:

```json
{
  "data": []
}
```

Expected:

```text
successful empty result
```

No exception.

---

## Basic Account Insights

Test normalization of:

```text
spend
impressions
reach
clicks
```

---

## Campaign-Level Insights

Test multiple Campaign rows and correct Campaign identity.

---

## Ad Set-Level Insights

Verify:

```text
campaignId
adSetId
```

relationships.

---

## Ad-Level Insights

Verify:

```text
campaignId
adSetId
adId
```

relationships.

---

## Custom Date Range

Verify exact:

```text
since
until
```

are sent to Meta.

---

## Invalid Date Range

Verify:

```text
since > until
```

fails before MetaClient is invoked.

---

## Aggregate Response

Verify a whole-period row is normalized correctly.

---

## Daily Breakdown

Given multiple daily rows, verify every day remains independently represented.

---

## Action Parsing

Use an `actions` fixture containing multiple unrelated actions.

Ensure purchase extraction is based on action type, not array position.

---

## Action Values

Verify purchase monetary value is extracted independently from purchase count.

---

## Derived Metrics

For known values:

```text
Spend = 100
Purchases = 4
Purchase Value = 300
Clicks = 20
Impressions = 1000
```

verify:

```text
CPP  = 25
ROAS = 3
CPC  = 5
CTR  = 2%
CPM  = 100
```

---

## Zero Denominators

Test:

```text
Purchases = 0
Spend = 0
Clicks = 0
Impressions = 0
```

Ensure no division warnings, Infinity, or NaN.

---

## Missing Values

Verify missing data remains semantically different from explicit zero where applicable.

---

## Metric Dependency Resolution

Request only:

```text
ROAS
```

and verify the request builder internally obtains the foundational data required to calculate it.

---

## Pagination

Use multiple fake pages and verify all rows are returned exactly once.

---

## Current Status Independence

Create a fixture where:

```text
status = PAUSED
```

but requested-period Insights contain delivery.

Ensure Insights data is not discarded because of current status.

---

## Creation Date Independence

Create a Campaign that predates the requested range and verify its requested-period Insights remain valid.

---

## Authentication Error

Verify proper exception mapping.

---

## Permission Error

Verify proper exception mapping.

---

## Rate Limit

Verify proper exception mapping without uncontrolled retry loops.

---

## Malformed Response

Verify unexpected Meta response structures fail predictably.

---

## Credential Leakage

Assert that token values never appear in exceptions or logs.

---

# 43. Real API Development Verification

The development Ad Account may currently legitimately return:

```json
{
  "data": []
}
```

for Campaigns or Insights.

Do not modify production logic merely to make an empty development account produce records.

Use mocked fixtures to develop parser/calculation behavior until real delivery data becomes available.

Live API testing should validate integration.

Unit tests should validate logic.

Keep those responsibilities separate.

---

# 44. Official Meta Documentation Requirement

Before implementing exact Meta-specific behavior, verify the current official Meta Marketing API documentation for:

* Insights endpoint structure
* supported API version
* `level`
* `fields`
* `time_range`
* `date_preset`
* `time_increment`
* filtering
* pagination
* `actions`
* `action_values`
* purchase action types
* attribution-related behavior
* available entity identifiers
* error structures

Do not copy old blog posts, Stack Overflow snippets, or outdated Graph API examples into the library as authoritative behavior.

Keep Meta-specific mappings centralized so future API-version changes are inexpensive.

---

# 45. Do Not Implement Outside Scope

Do NOT add:

* ecommerce order retrieval
* Pixel installation
* Conversions API implementation
* fbp/fbc matching
* UTM attribution engine
* organic vs paid classification
* product-wise ad-cost allocation
* profit calculations
* currency conversion
* dashboard UI
* controllers
* application routes
* database persistence

The Insights module understands **Meta advertising performance**.

It does not understand the consuming application's business domain.

---

# 46. Expected Consumer Experience

The consumer should eventually be able to express something conceptually equivalent to:

```php
$query = new InsightsQuery(
    level: InsightLevel::CAMPAIGN,
    dateRange: DateRange::between(
        '2026-09-01',
        '2026-09-24'
    ),
    metrics: [
        Metric::SPEND,
        Metric::PURCHASES,
        Metric::ROAS,
        Metric::IMPRESSIONS,
        Metric::CLICKS,
    ]
);

$results = $metaMetrics->insights()->get($query);
```

The exact public API may be adapted to the existing project architecture.

The important point is that the consumer should NOT need to know:

```text
Meta actions array structure
Meta action_values structure
raw field mappings
metric dependencies
pagination cursors
derived metric formulas
Meta response normalization rules
```

MetaMetrics owns those concerns.

---

# 47. Final Design Rule

Keep this distinction explicit throughout the implementation:

```text
MetaClient
    = How do we communicate with Meta?

InsightsQuery
    = What analytics does the consumer want?

InsightsParser
    = What does Meta's response mean?

InsightsNormalizer
    = How should MetaMetrics represent it?

MetricCalculator
    = What can be mathematically derived?

HistoricalService
    = Did this entity have relevant delivery during a period?
```

The final Insights pipeline should therefore remain:

```text
Request
   ↓
Resolve level/date/metrics/dependencies
   ↓
Build Meta request
   ↓
MetaClient
   ↓
Pagination
   ↓
Parse Meta-specific structures
   ↓
Normalize foundational metrics
   ↓
Calculate derived metrics
   ↓
Return stable InsightsDTO collection
```

Optimize for correctness, explicit semantics, testability, framework independence, and future Meta API changes rather than minimizing the number of classes.
