# MetaMetrics — Metrics Implementation Instructions

Implement the **Metrics layer** for MetaMetrics.

The Metrics layer defines the analytics vocabulary exposed by MetaMetrics and provides a centralized mechanism for:

* supported metric definitions
* Meta field requirements
* action-based metric requirements
* metric dependency resolution
* derived metric calculation
* numeric normalization
* missing-value semantics
* division-by-zero safety

The consumer should work with stable MetaMetrics metric concepts rather than Meta-specific field names or formulas.

---

# Implementation Status

The Metrics layer is implemented in these steps:

1. **Controlled metric model**
   `Metric`, `MetricType`, `MetricUnit`, `MetricAggregation`, and
   `MetricDefinition` define the supported vocabulary and its semantics.
2. **Authoritative registry**
   `MetricRegistry` validates the complete catalog, resolves recursive
   dependencies, removes duplicates deterministically, and returns the required
   Meta fields.
3. **Coordinated purchase mapping**
   `PurchaseActionMapping` provides one ordered mapping shared by
   `ActionParser` and `ActionValueParser`, keeping purchase count and value on
   the same action type.
4. **Pure metric calculation**
   `MetricCalculator` calculates derived metrics from normalized foundational
   values with consistent null, zero-denominator, and finite-number handling.
5. **Mathematically correct aggregation**
   `MetricAggregator` sums compatible foundational values and recalculates
   ratios. It never sums Reach across multiple reporting periods.
6. **Insights integration**
   `InsightsParser` normalizes foundational values, `InsightsNormalizer`
   calculates only requested output metrics, and `InsightsDTO` retains the
   foundations required for later aggregation.

## Metric Catalog

| Metric | Type | Unit | Meta field or dependencies | Aggregation |
| --- | --- | --- | --- | --- |
| Spend | Direct | Money | `spend` | Sum |
| Purchases | Action | Count | `actions` | Sum |
| Cost Per Purchase | Derived | Money | Spend, Purchases | Recalculate |
| Purchase Value | Action value | Money | `action_values` | Sum |
| ROAS | Derived | Ratio | Purchase Value, Spend | Recalculate |
| Impressions | Direct | Count | `impressions` | Sum |
| Reach | Direct | Count | `reach` | Meta only |
| Clicks | Direct | Count | `clicks` | Sum |
| Link Clicks | Direct | Count | `inline_link_clicks` | Sum |
| CPC | Derived | Money | Spend, Clicks | Recalculate |
| CTR | Derived | Percent | Clicks, Impressions | Recalculate |
| CPM | Derived | Money | Spend, Impressions | Recalculate |

## Registry Usage

```php
<?php

use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricRegistry;

$registry = new MetricRegistry();

$foundational = $registry->foundationalMetrics([
    Metric::ROAS,
    Metric::CTR,
]);

// [Metric::SPEND, Metric::PURCHASE_VALUE,
//  Metric::CLICKS, Metric::IMPRESSIONS]

$fields = $registry->metaFields([
    Metric::ROAS,
    Metric::CTR,
]);

// ['spend', 'action_values', 'clicks', 'impressions']
```

Use `MetricRegistry::fromName()` when a string enters through an application
boundary. It returns a controlled `Metric` value or throws
`UnsupportedMetricException`.

## Calculator Usage

```php
<?php

use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricCalculator;

$calculator = new MetricCalculator();

$roas = $calculator->calculate(Metric::ROAS, [
    Metric::PURCHASE_VALUE->value => 300.0,
    Metric::SPEND->value => 100.0,
]);

// 3.0
```

Inputs must already be normalized finite numbers or `null`. A missing input or
zero denominator returns `null`; a zero numerator with a non-zero denominator
returns `0.0`. Calculations do not round or format values.

## Purchase Mapping

The default mapping is ordered and exact. It does not use substring matching
and does not sum several purchase-like action types. To replace the mapping,
pass the same `PurchaseActionMapping` to both parsers:

```php
<?php

use MetaMetrics\Insights\Metric\PurchaseActionMapping;
use MetaMetrics\Insights\Parser\ActionParser;
use MetaMetrics\Insights\Parser\ActionValueParser;
use MetaMetrics\Insights\Parser\InsightsParser;

$mapping = new PurchaseActionMapping([
    'offsite_conversion.fb_pixel_purchase',
    'omni_purchase',
]);

$parser = new InsightsParser(
    actionParser: new ActionParser($mapping),
    actionValueParser: new ActionValueParser($mapping),
);
```

When both `actions` and `action_values` are available, the parser selects one
configured action type present in both arrays. When either array is missing,
the available metric is still normalized independently and the missing metric
remains `null`.

### Meta Mapping Verification

Verification recorded on 2026-09-24:

* Meta's current generated Business SDK defines `spend`, `actions`,
  `action_values`, `impressions`, `reach`, `clicks`, and
  `inline_link_clicks` as Ads Insights fields.
* The generated model represents `actions` and `action_values` as lists of
  action statistics, but it does not publish a closed, versioned enum of every
  possible `action_type` returned for all attribution and event-source
  contexts.
* Consequently, `PurchaseActionMapping::DEFAULT_ACTION_TYPES` is an explicit,
  ordered MetaMetrics interpretation policy rather than an assumed exhaustive
  Meta schema. It uses exact matches, selects one type, and is replaceable by
  consumers when their reporting context requires a narrower mapping.

Primary field reference:
[Meta Business SDK `AdsInsights`](https://github.com/facebook/facebook-python-business-sdk/blob/main/facebook_business/adobjects/adsinsights.py).

The API version in `MetaConfig` remains authoritative for requests. When that
version changes, review the centralized definitions in `MetricRegistry` and
the default purchase policy against the corresponding Meta release before
changing mappings. Version-specific conditions must not be added to services.

## Aggregating Rows

```php
<?php

use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricAggregator;

$result = (new MetricAggregator())->aggregate($dailyInsights, [
    Metric::SPEND,
    Metric::CTR,
    Metric::ROAS,
]);

if ($result !== null) {
    $periodCtr = $result->metric(Metric::CTR);
}
```

Rows must belong to one entity and level and must not overlap. An empty row set
returns `null`. Reach is retained for one row, but returns `null` when combining
multiple rows because unique people cannot be reconstructed by summation. Fetch
Meta's aggregate Insights row for the complete period when period Reach is
required.

---

# 1. Primary Goal

The Metrics layer should provide a transformation such as:

```text
Consumer requests metrics
        ↓
MetricRegistry
        ↓
Resolve dependencies
        ↓
Determine required Meta data
        ↓
InsightsService fetches data
        ↓
Normalize foundational values
        ↓
MetricCalculator
        ↓
Final normalized metrics
```

Example:

```text
Consumer requests:

ROAS
CTR
```

MetaMetrics should understand internally that these require:

```text
ROAS
 ├── Spend
 └── Purchase Conversion Value

CTR
 ├── Clicks
 └── Impressions
```

The consumer must not manually request those dependencies.

---

# 2. Supported Metrics

The initial normalized metric catalog should cover:

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

Keep the metric catalog centralized.

Do not scatter metric names or definitions across:

```text
InsightsService
CampaignService
AdSetService
AdService
HistoricalService
```

---

# 3. Metric Enum / Controlled Identity

Use a controlled representation for supported metrics.

Prefer an enum if compatible with the project's PHP version.

Conceptually:

```php
Metric::SPEND
Metric::PURCHASES
Metric::COST_PER_PURCHASE
Metric::PURCHASE_VALUE
Metric::ROAS
Metric::IMPRESSIONS
Metric::REACH
Metric::CLICKS
Metric::LINK_CLICKS
Metric::CPC
Metric::CTR
Metric::CPM
```

Exact enum case naming may follow the existing code conventions.

Do not use arbitrary consumer-provided strings as internal metric identifiers.

If a public API accepts strings, validate and convert them into controlled Metric values at the boundary.

Unknown metrics should produce:

```text
UnsupportedMetricException
```

rather than being silently ignored.

---

# 4. Metric Categories

Explicitly distinguish at least these concepts:

```text
Direct field metric
Action-based metric
Action-value metric
Derived metric
```

Examples:

```text
Spend
    → direct/foundational

Impressions
    → direct/foundational

Clicks
    → direct/foundational

Purchases
    → action-based foundational metric

Purchase Conversion Value
    → action-value foundational metric

ROAS
    → derived

Cost Per Purchase
    → derived

CPC
    → derived

CTR
    → derived

CPM
    → derived
```

This distinction should be represented in the metric architecture rather than inferred using scattered conditional statements.

---

# 5. Foundational vs Derived Metrics

A foundational metric is obtained from normalized Meta data.

A derived metric is calculated from one or more foundational metrics.

Conceptually:

```text
Meta API
   ↓
Foundational metrics
   ↓
MetricCalculator
   ↓
Derived metrics
```

For the initial implementation:

```text
FOUNDATIONAL

Spend
Purchases
Purchase Conversion Value
Impressions
Reach
Clicks
Link Clicks
```

and:

```text
DERIVED

Cost Per Purchase
ROAS
CPC
CTR
CPM
```

Do not calculate derived metrics inside parsers or normalizers.

---

# 6. MetricRegistry

`MetricRegistry` should be the authoritative definition source for metric behavior.

It should be capable of answering questions such as:

```text
Is this metric supported?

What kind of metric is it?

Which foundational metrics does it depend on?

Which Meta field(s) are required?

Does it require actions?

Does it require action_values?

Is it derived?
```

Avoid a large `switch` duplicated across multiple services.

Metric-specific metadata should live centrally.

---

# 7. Metric Definition Model

Use a clean internal definition structure if useful.

Conceptually, a definition may contain:

```text
metric
type
dependencies
Meta field requirements
parser requirements
```

For example:

```text
SPEND

type:
DIRECT

Meta requirements:
spend

dependencies:
none
```

while:

```text
ROAS

type:
DERIVED

dependencies:
SPEND
PURCHASE_VALUE

direct Meta field:
none required for calculation
```

and:

```text
PURCHASES

type:
ACTION

Meta requirements:
actions

parser:
ActionParser
```

Do not expose internal Meta mapping details unnecessarily through the public API.

---

# 8. Recursive Dependency Resolution

Metric dependency resolution must support derived metrics recursively.

Example:

```text
Consumer requests:

ROAS
COST_PER_PURCHASE
CTR
```

Dependencies become:

```text
ROAS
 ├── Spend
 └── Purchase Value

Cost Per Purchase
 ├── Spend
 └── Purchases

CTR
 ├── Clicks
 └── Impressions
```

The effective foundational set becomes:

```text
Spend
Purchase Value
Purchases
Clicks
Impressions
```

Resolve duplicates.

Do not request:

```text
Spend
Spend
Spend
```

multiple times.

---

# 9. Requested Metrics vs Required Metrics

Preserve the distinction between:

```text
metrics requested by consumer
```

and:

```text
metrics internally required to calculate them
```

Example:

```text
Requested:
ROAS
```

Internally required:

```text
Spend
Purchase Value
```

The library may fetch the dependencies without necessarily exposing them as explicitly requested top-level metrics if the public result model supports selective output.

Do not confuse API fetch requirements with consumer output requirements.

---

# 10. Meta Field Resolution

The Metrics layer should be able to resolve normalized metrics into the minimum Meta fields required to obtain them.

Example concept:

```text
Spend
    → Meta spend field

Impressions
    → Meta impressions field

Purchases
    → Meta actions structure

Purchase Value
    → Meta action_values structure
```

Exact Meta field names and availability must be verified against the current official Meta Marketing API documentation.

Do not invent or permanently hardcode assumptions based on old API examples without verification.

Keep mappings centralized so future API version changes are manageable.

---

# 11. Do Not Automatically Use Meta's Precomputed Ratio Metrics

Meta may expose fields corresponding to metrics such as:

```text
CPC
CTR
CPM
ROAS-related values
cost per action
```

MetaMetrics also has enough foundational data to derive some of these metrics.

Do not unpredictably alternate between:

```text
Meta-returned CPC
```

and:

```text
MetaMetrics-calculated CPC
```

under the same normalized metric.

For deterministic basic metrics, prefer the project's explicitly defined formulas where appropriate.

If direct Meta-calculated variants are introduced later, represent them separately and document their semantics.

---

# 12. Spend

Normalized:

```text
Spend
```

represents Meta-reported advertising spend for the requested reporting context.

Do not:

* perform currency conversion
* attach a hardcoded currency
* round for display
* mix spend from incompatible reporting periods

The Ad Account determines reporting currency.

---

# 13. Impressions

Normalize Meta impression data consistently.

This is a count metric.

Do not confuse:

```text
Impressions
```

with:

```text
Reach
```

They represent different concepts.

Keep them as separate metrics even when values happen to be similar.

---

# 14. Reach

Normalize Reach independently.

Do not derive Reach from impressions.

Do not assume:

```text
reach = impressions
```

or attempt to mathematically reconstruct Reach.

Use Meta's reported Reach value where supported.

---

# 15. Clicks

Normalize the project's general Clicks metric from the verified Meta field corresponding to the intended definition.

Do not silently substitute Link Clicks for Clicks.

They are separate normalized metrics.

---

# 16. Link Clicks

Treat Link Clicks separately from general Clicks.

Depending on current Meta API behavior, this metric may require:

* a direct field
* an action-based interpretation
* another documented Meta representation

Verify the exact current source before implementation.

Do not assume an outdated field/action mapping.

Centralize the mapping in MetricRegistry/parser configuration.

---

# 17. Purchases

The normalized metric should represent:

```text
Meta-attributed Purchases
```

for the requested Insights context.

It does NOT mean:

```text
all orders in the consumer's ecommerce database
```

and does not establish independent first-party attribution truth.

Purchase extraction may require Meta's `actions` structure or its current equivalent.

Delegate extraction to:

```text
ActionParser
```

rather than embedding purchase action logic in MetricCalculator.

---

# 18. Purchase Action Mapping

Purchase mapping requires special care.

Meta may expose multiple purchase-related action types depending on:

* event source
* attribution configuration
* reporting behavior
* API version

Do not implement:

```php
if (str_contains($actionType, 'purchase')) {
    $purchases += $value;
}
```

This can produce double counting or semantically mixed results.

Instead:

```text
MetricRegistry / dedicated action mapping
        ↓
supported purchase action semantics
        ↓
ActionParser
```

The exact mapping must be verified against current official Meta Marketing API documentation.

Keep the mapping replaceable/configurable internally so Meta API changes do not require rewriting the entire Insights pipeline.

---

# 19. Purchase Conversion Value

Normalize:

```text
Purchase Conversion Value
```

as the monetary value attributed by Meta to the supported purchase event semantics.

This may come from:

```text
action_values
```

or the current documented Meta equivalent.

Use:

```text
ActionValueParser
```

Do not derive purchase value from:

```text
purchase count × assumed order value
```

MetaMetrics does not know the consumer application's order values.

---

# 20. Purchase Count and Purchase Value Must Align

Purchase count and Purchase Conversion Value should represent compatible purchase-event semantics.

Avoid a situation where:

```text
Purchases
```

uses one action definition while:

```text
Purchase Value
```

uses an unrelated purchase action definition.

Otherwise:

```text
ROAS
```

and:

```text
Cost Per Purchase
```

can become semantically inconsistent.

Keep their action mappings coordinated.

---

# 21. Cost Per Purchase

Define:

```text
Cost Per Purchase = Spend / Purchases
```

Example:

```text
Spend = 1000
Purchases = 20

CPP = 50
```

If:

```text
Purchases = 0
```

then Cost Per Purchase is mathematically unavailable.

Return:

```text
null
```

unless another explicit project-wide convention has already been established.

Do not return Infinity or NaN.

---

# 22. ROAS

Define:

```text
ROAS = Purchase Conversion Value / Spend
```

Example:

```text
Purchase Value = 3000
Spend = 1000

ROAS = 3
```

Interpretation:

```text
3x
```

is presentation-level wording.

The normalized numeric value should remain:

```text
3
```

Do not store:

```text
"3x"
```

inside the analytics DTO.

If:

```text
Spend = 0
```

ROAS is unavailable.

Return:

```text
null
```

rather than Infinity.

---

# 23. CPC

Define:

```text
CPC = Spend / Clicks
```

Example:

```text
Spend = 500
Clicks = 100

CPC = 5
```

If:

```text
Clicks = 0
```

return:

```text
null
```

for CPC.

Do not substitute Link Clicks into this formula unless the metric is explicitly defined as a separate Link CPC metric in the future.

---

# 24. CTR

Define:

```text
CTR = (Clicks / Impressions) × 100
```

Example:

```text
Clicks = 200
Impressions = 10,000

CTR = 2
```

This represents:

```text
2%
```

The normalized numeric value should be:

```text
2
```

not:

```text
0.02
```

and not:

```text
"2%"
```

Document this convention clearly because percentage scaling ambiguity can cause consumer bugs.

If:

```text
Impressions = 0
```

return:

```text
null
```

for CTR.

---

# 25. CPM

Define:

```text
CPM = (Spend / Impressions) × 1000
```

Example:

```text
Spend = 1000
Impressions = 100,000

CPM = 10
```

If:

```text
Impressions = 0
```

return:

```text
null
```

for CPM.

---

# 26. MetricCalculator

`MetricCalculator` owns mathematical derivation.

It should accept normalized foundational values and calculate requested derived metrics.

It must NOT:

* perform HTTP requests
* inspect access tokens
* parse raw Meta API envelopes
* traverse pagination
* know CampaignService internals
* know ecommerce orders
* perform currency conversion

It should behave as a deterministic calculation component.

Conceptually:

```text
normalized foundational metrics
            +
requested derived metric
            ↓
MetricCalculator
            ↓
numeric result or null
```

---

# 27. MetricCalculator Must Be Pure Where Practical

Prefer calculations that depend only on provided inputs.

Example:

```text
calculate(ROAS, values)
```

should not depend on:

```text
current time
environment variables
database state
HTTP requests
global configuration
```

This makes metric calculation easy to test.

---

# 28. Safe Division

Centralize safe division behavior.

Do not repeat:

```php
if ($denominator === 0) ...
```

independently in five formulas if a clean internal helper can guarantee consistent semantics.

Conceptually:

```text
safeDivide(numerator, denominator)
```

should handle:

```text
zero denominator
missing numerator
missing denominator
invalid numeric value
```

predictably.

Never produce:

```text
INF
-INF
NaN
PHP division warnings
```

---

# 29. Missing Values vs Explicit Zero

Preserve the difference between:

```text
missing
```

and:

```text
0
```

where possible.

Example:

```text
Purchases = null
```

may mean the metric was unavailable/not returned.

While:

```text
Purchases = 0
```

means the metric is known to be zero under the established normalization semantics.

MetricCalculator should not blindly convert:

```text
null → 0
```

because doing so may produce misleading derived metrics.

---

# 30. Derived Metric With Missing Dependency

Example:

```text
Spend = 100
Purchases = null
```

Cost Per Purchase should be:

```text
null
```

not:

```text
0
```

and not:

```text
100
```

Similarly:

```text
Purchase Value = null
Spend = 100
```

should produce:

```text
ROAS = null
```

---

# 31. Zero Numerator Is Valid

Do not confuse a zero numerator with an invalid calculation.

Example:

```text
Purchase Value = 0
Spend = 100
```

Then:

```text
ROAS = 0
```

is valid.

Likewise:

```text
Clicks = 0
Impressions = 1000
```

Then:

```text
CTR = 0
```

is mathematically valid.

The denominator determines whether division is defined.

This distinction must be covered by tests.

---

# 32. Numeric Parsing

Meta may return numbers as strings:

```json
{
  "spend": "1500.75",
  "impressions": "10000",
  "clicks": "250"
}
```

Normalize these consistently before calculations.

Do not rely on uncontrolled PHP implicit type coercion throughout the library.

Validate numeric strings before conversion.

Malformed numeric values should be treated according to the project's malformed-response/error strategy rather than silently becoming zero.

---

# 33. IDs Are Not Metrics

Do not pass identifiers through numeric metric normalization.

For example:

```text
campaign_id
adset_id
ad_id
account_id
```

must remain strings.

Even if an ID contains only digits, never cast it because it "looks numeric".

---

# 34. Monetary Precision

Metrics such as:

```text
Spend
Purchase Value
Cost Per Purchase
CPC
CPM
```

are monetary values.

Avoid arbitrary rounding during parsing/calculation.

For example, do not automatically transform:

```text
12.345678
```

into:

```text
12.35
```

inside MetricCalculator unless the library contract explicitly defines such precision.

Display rounding belongs primarily to the consumer.

Choose a consistent internal numeric representation appropriate for the current PHP library design.

---

# 35. Percentage Precision

Likewise, do not arbitrarily round CTR.

If the calculation produces:

```text
1.234567
```

preserve the calculated value according to the library's numeric strategy.

The consumer may later display:

```text
1.23%
```

Presentation formatting is outside the Metrics layer.

---

# 36. Currency Context

The Metrics layer should not assume:

```text
USD
BDT
EUR
```

Currency comes from the Ad Account.

Do not infer currency from:

* user location
* account timezone
* application locale

Do not convert monetary metrics between currencies.

A BDT account's:

```text
Spend = 1000
```

means 1000 units of the account's reporting currency.

The account/Insights context may expose the currency separately when needed.

---

# 37. Metric Aggregation Semantics

Do not assume every metric can be aggregated by simple summation.

Additive foundational metrics such as:

```text
Spend
Impressions
Clicks
Purchases
Purchase Value
```

may be aggregatable in appropriate contexts.

But ratio metrics such as:

```text
ROAS
CPC
CTR
CPM
Cost Per Purchase
```

must NOT normally be aggregated by averaging or summing their row-level values.

Wrong:

```text
Day 1 CTR = 1%
Day 2 CTR = 5%

Period CTR = (1 + 5) / 2
```

This can be mathematically wrong.

Instead derive the period metric from period-level foundational totals:

```text
Period CTR =
Total Clicks / Total Impressions × 100
```

Apply the same principle to other derived metrics.

---

# 38. Never Sum Reach Naively Across Time

Reach requires special care.

Do not assume:

```text
Period Reach =
Day 1 Reach + Day 2 Reach + ...
```

because the same person may appear across multiple periods.

Prefer Meta's aggregate Reach for the requested period when period-level Reach is needed.

Do not implement naive Reach aggregation in MetricCalculator.

---

# 39. Derived Metric Recalculation After Aggregation

If MetaMetrics ever aggregates normalized rows internally, the correct conceptual order should be:

```text
aggregate compatible foundational metrics
        ↓
recalculate derived metrics
```

rather than:

```text
calculate derived metrics per row
        ↓
average/sum those ratios
```

This is important for mathematically correct reporting.

---

# 40. Metric Selection

Consumers should be able to request a subset such as:

```text
Spend
Purchases
ROAS
```

The library should resolve only the necessary data.

Example:

```text
Requested:
Spend
ROAS

Required foundational:
Spend
Purchase Value
```

Do not request:

```text
Reach
Clicks
CTR
CPM
```

when they are unrelated.

---

# 41. Historical Metric Dependencies

Historical delivery discovery may require metrics that the consumer did not explicitly request.

Example:

```text
Consumer asks historical Campaigns
and only wants:
Purchases
```

DeliveryDiscovery may internally require:

```text
Impressions
Spend
```

depending on the configured delivery-evidence rule.

The effective metric requirements should therefore combine:

```text
consumer requested metrics
        +
Historical delivery evidence dependencies
```

without exposing this complexity to the consumer.

---

# 42. Avoid Circular Dependencies

MetricRegistry must detect/prevent circular metric dependencies.

For example, a broken future configuration such as:

```text
Metric A depends on Metric B
Metric B depends on Metric A
```

must not cause infinite recursion.

Built-in metric definitions should be validated during development/tests.

Dependency resolution should be deterministic.

---

# 43. Stable Ordering

When resolving metric dependencies, produce deterministic output ordering where practical.

Do not allow hash/map iteration order to create unpredictable Meta request field ordering or test instability.

Semantic behavior matters more than field order, but deterministic construction improves testing and debugging.

---

# 44. Unsupported Metrics

If a consumer requests:

```text
Metric not supported by MetaMetrics
```

fail explicitly with:

```text
UnsupportedMetricException
```

Do not:

* silently drop it
* send an unknown field to Meta
* return null pretending it was supported

The exception should identify the unsupported normalized metric safely.

---

# 45. API-Version Awareness

Meta field availability can change between Marketing API versions.

Do not spread version-specific metric logic across the application.

Keep Meta-specific mappings sufficiently centralized that a future version change can be handled without rewriting:

```text
InsightsService
HistoricalService
CampaignService
AdSetService
AdService
```

The configured Meta API version remains authoritative for API requests.

---

# 46. Attribution Semantics

Do not claim that MetaMetrics' purchase metrics represent objective business-wide attribution.

They represent Meta-reported/Meta-attributed performance under the applicable Insights/attribution context.

Do not combine them automatically with:

```text
Shopify orders
WooCommerce orders
custom ecommerce orders
payment records
fbp/fbc
UTM attribution
```

That belongs to a consuming analytics application.

---

# 47. Metric Documentation Metadata

Where useful, MetricRegistry may expose safe descriptive metadata such as:

```text
normalized name
category
unit
derived/direct
dependencies
```

Example:

```text
CTR

unit:
percent

derived:
true

dependencies:
Clicks, Impressions
```

Avoid exposing implementation-sensitive Meta internals unless needed.

This can later help consumers build dynamic reporting interfaces without hardcoding metric semantics.

---

# 48. Units

Keep metric units explicit internally where useful.

Conceptually:

```text
Spend
    → MONEY

Purchases
    → COUNT

Purchase Value
    → MONEY

ROAS
    → RATIO

Impressions
    → COUNT

Reach
    → COUNT

Clicks
    → COUNT

Link Clicks
    → COUNT

CPC
    → MONEY

CTR
    → PERCENT

CPM
    → MONEY
```

Cost Per Purchase is also:

```text
MONEY
```

Do not embed display symbols such as:

```text
৳
$
%
x
```

inside normalized numeric values.

---

# 49. Metric Result Semantics

A normalized metric value should remain machine-friendly.

Prefer:

```php
'ctr' => 2.35
'roas' => 3.2
'spend' => 1500.75
```

rather than:

```php
'ctr' => '2.35%'
'roas' => '3.2x'
'spend' => '৳1,500.75'
```

Formatting belongs to the consumer application.

---

# 50. Testing — Registry

Test that every supported Metric has a valid registry definition.

Verify:

* no duplicate definitions
* valid category/type
* valid dependencies
* no circular dependencies
* required parser/field metadata exists where applicable

---

# 51. Testing — Dependency Resolution

Test:

```text
ROAS
```

resolves to:

```text
Spend
Purchase Value
```

Test:

```text
CTR
```

resolves to:

```text
Clicks
Impressions
```

Test multiple requested metrics and verify shared dependencies are deduplicated.

---

# 52. Testing — Spend

Verify numeric Meta string normalization.

Example:

```text
"1500.50"
```

is handled correctly.

---

# 53. Testing — Purchases

Use realistic action arrays containing:

```text
purchase-related action
unrelated actions
multiple entries
```

Verify extraction uses the supported action mapping rather than array position.

---

# 54. Testing — Purchase Value

Test action-value extraction separately from purchase-count extraction.

Do not assume both arrays have identical ordering.

---

# 55. Testing — Cost Per Purchase

Given:

```text
Spend = 1000
Purchases = 20
```

expect:

```text
50
```

Given:

```text
Purchases = 0
```

expect:

```text
null
```

---

# 56. Testing — ROAS

Given:

```text
Purchase Value = 3000
Spend = 1000
```

expect:

```text
3
```

Given:

```text
Purchase Value = 0
Spend = 1000
```

expect:

```text
0
```

Given:

```text
Spend = 0
```

expect:

```text
null
```

---

# 57. Testing — CPC

Given:

```text
Spend = 500
Clicks = 100
```

expect:

```text
5
```

Given:

```text
Clicks = 0
```

expect:

```text
null
```

---

# 58. Testing — CTR

Given:

```text
Clicks = 200
Impressions = 10000
```

expect:

```text
2
```

representing 2%.

Given:

```text
Clicks = 0
Impressions = 10000
```

expect:

```text
0
```

Given:

```text
Impressions = 0
```

expect:

```text
null
```

---

# 59. Testing — CPM

Given:

```text
Spend = 1000
Impressions = 100000
```

expect:

```text
10
```

Given:

```text
Impressions = 0
```

expect:

```text
null
```

---

# 60. Testing — Missing Dependencies

Test:

```text
Spend = 100
Purchases = null
```

expect:

```text
CPP = null
```

Test:

```text
Spend = null
Clicks = 10
```

expect:

```text
CPC = null
```

---

# 61. Testing — Malformed Numeric Data

Test values such as:

```text
"abc"
""
unexpected arrays
unexpected objects
```

Do not silently normalize malformed values to zero.

Use the project's established malformed-response behavior.

---

# 62. Testing — Aggregation Mathematics

Test that derived metrics are calculated from aggregated foundational values rather than averaging row-level ratios.

Example:

```text
Day 1:
Clicks = 10
Impressions = 100
CTR = 10%

Day 2:
Clicks = 10
Impressions = 900
CTR ≈ 1.111%
```

Correct period CTR:

```text
20 / 1000 × 100
= 2%
```

Do NOT calculate:

```text
(10% + 1.111%) / 2
```

as the period CTR.

---

# 63. Testing — Reach

Ensure the implementation does not blindly sum daily Reach to produce period Reach.

---

# 64. Testing — Unsupported Metric

Verify an unsupported metric results in:

```text
UnsupportedMetricException
```

before an unnecessary Meta API request is performed where possible.

---

# 65. Testing — Empty Insights

A successful response:

```json
{
  "data": []
}
```

must not cause metric calculation errors.

The result should remain an empty normalized Insights collection.

Do not manufacture a row containing all zero metrics unless such behavior is explicitly requested by a separate API.

---

# 66. Official Meta Documentation Verification

Before implementing exact Meta-specific mappings, verify the current official Meta Marketing API documentation for:

* Insights metric fields
* `actions`
* `action_values`
* purchase action types
* link-click representation
* attribution behavior
* monetary value behavior
* field availability by Insights level
* API-version differences

Do not use old examples as authoritative mappings.

The normalized metric API should remain stable even when internal Meta mappings need to change.

---

# 67. Do Not Implement Outside Scope

The Metrics layer must NOT calculate:

```text
Profit
Gross Profit
Net Profit
Product Profitability
Employee Commission
Order-level Ad Cost Allocation
Organic vs Paid Revenue
Business-wide Conversion Rate
Customer Acquisition Cost from external order data
Lifetime Value
```

unless these are explicitly introduced into MetaMetrics scope later.

The current library understands Meta advertising metrics.

It does not understand the consumer application's business economics.

---

# 68. Responsibility Map

Maintain this separation:

```text
Metric
    → normalized metric identity

MetricRegistry
    → definitions, types, dependencies,
      required Meta data

MetricCalculator
    → mathematical derivation

ActionParser
    → action count extraction

ActionValueParser
    → action monetary-value extraction

InsightsParser
    → raw Insights interpretation

InsightsNormalizer
    → normalized foundational values

InsightsService
    → request orchestration

HistoricalService
    → historical delivery discovery
```

Do not move all metric behavior into `InsightsService`.

---

# 69. Core Invariants

Preserve these invariants:

```text
Normalized metric
≠
raw Meta field name
```

```text
Requested metric
≠
all internally required metrics
```

```text
Purchase
=
Meta-attributed purchase
≠
consumer application's total orders
```

```text
Missing
≠
Zero
```

```text
Undefined ratio
≠
Zero ratio
```

```text
Derived period metric
≠
average of row-level derived metrics
```

These distinctions are essential for analytics correctness.

---

# 70. Final Design Principle

The consumer should be able to request:

```text
Spend
Purchases
Cost Per Purchase
ROAS
CTR
```

without knowing that internally MetaMetrics may need:

```text
spend
actions
action_values
clicks
impressions
```

or whatever exact current Meta fields are required.

The final architecture should behave conceptually as:

```text
Consumer Metric Vocabulary
          ↓
MetricRegistry
          ↓
Dependency Resolution
          ↓
Meta Field / Parser Requirements
          ↓
Insights Retrieval
          ↓
Foundational Metric Normalization
          ↓
MetricCalculator
          ↓
Stable MetaMetrics Metrics
```

Meta-specific representation should remain an implementation detail.

The Metrics layer should provide a stable, mathematically explicit analytics vocabulary on top of Meta Marketing API data.
