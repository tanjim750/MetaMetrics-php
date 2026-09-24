Implement the **Ad layer** for MetaMetrics.

MetaMetrics should provide a normalized, framework-independent abstraction for retrieving Meta Ads Ad data while preserving its relationship with the parent Ad Set and Campaign.

## Core Responsibilities

Support:

* Retrieve Ads from the configured Ad Account
* Retrieve Ads belonging to a specific Campaign
* Retrieve Ads belonging to a specific Ad Set
* Retrieve an individual Ad
* Ad ID
* Ad Name
* Current Status
* Effective Status where available
* Parent Ad Set relationship
* Parent Campaign relationship
* Relevant Ad metadata
* Ad-level performance insights
* Date-range based analytics
* Filtering and field selection
* Normalized Ad output

Conceptually:

```text
Consumer
    ↓
AdService
    ↓
MetaClient
    ↓
Meta Marketing API
    ↓
AdNormalizer
    ↓
AdDTO
```

## AdService

Create a focused service for Ad operations.

All Meta API communication must use the shared `MetaClient`.

The service should provide operations conceptually equivalent to:

```text
Retrieve Ads

Retrieve Ads by Campaign

Retrieve Ads by Ad Set

Retrieve an individual Ad

Retrieve Ad-level Insights
```

Use the project's established naming conventions for actual method names.

Do not implement HTTP transport, pagination, authentication, metric parsing, or historical discovery directly inside `AdService`.

## AdDTO

Create an immutable normalized representation of an Ad.

Core fields should include, where available:

```text
id
name
adSetId
campaignId
status
effectiveStatus
createdTime
updatedTime
```

Additional useful Ad metadata may be included when relevant and supported by the current Meta Marketing API.

Do not blindly copy every Meta field into the DTO.

Keep the normalized representation focused on useful entity information.

## Parent Relationships

Every normalized Ad should preserve its hierarchy.

```text
Campaign
    ↓
Ad Set
    ↓
Ad
```

At minimum preserve:

```text
campaignId
adSetId
```

when available from Meta.

This allows the consumer to understand:

```text
Which Ad Set owns this Ad?

Which Campaign does this Ad ultimately belong to?
```

Do not automatically fetch full Campaign or Ad Set objects when retrieving an Ad.

Relationship IDs are sufficient for normal entity representation.

Full nested resource loading belongs to the hierarchy layer.

## Ad Normalization

Raw Meta responses must be normalized before being exposed to normal consumers.

```text
Raw Meta Ad
      ↓
AdNormalizer
      ↓
AdDTO
```

The normalizer should:

* preserve IDs as strings
* preserve `adSetId`
* preserve `campaignId`
* safely handle optional fields
* normalize timestamps consistently
* preserve status distinctions
* avoid inventing missing values
* detect malformed essential response structures

Do not put HTTP request logic inside the normalizer.

## Ad Collection

Support retrieving Ads belonging to the configured Ad Account.

Use Meta-supported collection requests.

Pagination must use the shared paginator.

Consumers should not need to manually follow Meta paging cursors.

## Campaign-Specific Ads

Support retrieving Ads associated with a Campaign.

Conceptually:

```text
Campaign ID
    ↓
AdService
    ↓
Meta API
    ↓
Ads
```

Do not retrieve every Ad from the account and filter them locally when Meta provides an appropriate Campaign-scoped query.

Validate obvious invalid local input such as an empty Campaign ID.

## Ad Set-Specific Ads

Support retrieving Ads belonging to a specific Ad Set.

Conceptually:

```text
Ad Set ID
    ↓
AdService
    ↓
Meta API
    ↓
Ads
```

Preserve both parent relationships in normalized results:

```text
campaignId
adSetId
```

Do not require the consumer to perform another request merely to determine the Ad's parent hierarchy when Meta already provides that information.

## Individual Ad

Support retrieving an Ad by ID.

Validate obvious local input errors before making the API request.

Meta remains the source of truth for:

* whether the Ad exists
* whether the configured token can access it
* whether the Ad belongs to an accessible account

Use the existing exception architecture for failures.

## Current Status vs Historical Delivery

Do not use an Ad's current status to determine whether it delivered during a historical period.

Example:

```text
Requested Period:
September 1 → September 24

Ad A
Delivered: September 3–15
Current Status: PAUSED
```

Ad A is historically relevant.

Likewise:

```text
Ad B
Current Status: ACTIVE
No delivery during September 1–24
```

Current `ACTIVE` status does not prove that Ad B delivered during the requested period.

Therefore:

```text
Current Status
      ≠
Historical Delivery
```

This distinction must remain explicit.

## Historical Ad Discovery

Historical relevance must be determined using date-scoped delivery/performance evidence through the shared Insights/Historical architecture.

Do not determine historical delivery solely from:

```text
status
effective_status
created_time
updated_time
```

Metadata may describe the entity, but it does not prove actual delivery.

Do not duplicate historical discovery logic inside `AdService`.

## Ad-Level Insights

Ad-level analytics must use the shared Insights layer.

Conceptually:

```text
Ad
+
Date Range
+
Selected Metrics
        ↓
Ad Insights
```

Relevant metrics may include:

```text
Spend
Purchases
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

Do not implement separate Ad-specific:

```text
ActionParser
ActionValueParser
MetricCalculator
InsightsNormalizer
```

Reuse the shared analytics components.

## Date Scoping

Ad creation date and requested Insights period are separate concepts.

Example:

```text
Ad created:
August 20

Requested Insights:
September 1 → September 24
```

Return only performance belonging to:

```text
September 1 → September 24
```

Do not include August performance.

The requested reporting period is authoritative.

## Filtering

Integrate with the shared query/filter architecture.

Potential Ad filtering concerns may include:

```text
Campaign
Ad Set
Ad ID
Status
Effective Status
Selected Fields
Other Meta-supported filters
```

Only implement filters actually supported by the current Meta API and MetaMetrics architecture.

Do not build a separate filtering mechanism inside `AdService`.

## Field Selection

Do not request every available Meta Ad field by default.

Define a sensible default field set needed for normalization.

Allow explicit field selection where supported by the existing query architecture.

Keep Meta-specific field definitions centralized where practical.

## Creative Data Boundary

An Ad may reference a Meta Ad Creative.

Do not automatically turn the Ad layer into a full Creative management system.

If creative identity or lightweight metadata is useful and directly available, it may be represented appropriately.

However, features such as:

```text
Creative management
Creative creation
Image/video upload
Creative editing
Asset management
```

are outside the current Ad retrieval responsibility unless separately introduced into MetaMetrics scope.

Avoid expanding the library unintentionally.

## Hierarchy Integration

Maintain:

```text
Ad Account
    ↓
Campaign
    ↓
Ad Set
    ↓
Ad
```

The Ad layer represents the leaf entity.

It should integrate cleanly with `HierarchyService`, but normal Ad retrieval must not automatically load the complete hierarchy.

For example:

```text
getAd($id)
```

should not cause:

```text
Ad request
+
Ad Set request
+
Campaign request
+
Insights request
```

unless explicitly requested by the consumer.

## Avoid N+1 Requests

Do not design collection retrieval such that every returned Ad automatically triggers additional API calls.

Avoid patterns like:

```text
500 Ads
    ↓
500 Ad Set requests
+
500 Campaign requests
+
500 Insights requests
```

Use relationship IDs already available in collection responses and Meta-supported bulk/level queries.

Metadata and analytics retrieval should remain independently controllable.

## Raw Response

Normal operations should return normalized MetaMetrics data.

Do not store the complete raw Meta response inside `AdDTO`.

If raw-response access exists for advanced/debugging purposes, use the shared client/debugging mechanism.

## Error Handling

Use the existing MetaMetrics exception hierarchy.

Handle cases such as:

```text
Authentication failure
Missing permission
Invalid/inaccessible Ad Account
Invalid/inaccessible Campaign
Invalid/inaccessible Ad Set
Invalid/inaccessible Ad
Invalid input
Rate limit
Network failure
Malformed Meta response
```

Do not create duplicate Ad-specific exceptions unless they provide genuinely different semantics.

Never expose access tokens through errors or logs.

## Tests

Add comprehensive unit tests using mocked/fake Meta responses.

No real Meta credentials should be required.

Cover at minimum:

### Ad Collection

Verify multiple Ads are correctly normalized.

### Campaign-Specific Retrieval

Verify Ads can be retrieved for a Campaign.

### Ad Set-Specific Retrieval

Verify Ads can be retrieved for an Ad Set.

### Individual Ad

Verify lookup by Ad ID.

### Parent Relationships

Verify:

```text
campaignId
adSetId
```

are correctly preserved.

### Optional Fields

Verify missing optional fields do not break normalization.

### Status

Verify current/effective status is preserved without being interpreted as historical delivery.

### Pagination

Verify shared pagination is used correctly.

### Date-Scoped Insights

Verify the exact requested period reaches the shared Insights layer.

### Historical Boundary

Verify `ACTIVE`, `PAUSED`, or other current states do not independently determine historical relevance.

### Error Mapping

Cover:

```text
Authentication failure
Permission failure
Campaign inaccessible
Ad Set inaccessible
Ad inaccessible
Rate limit
Network failure
```

### Credential Safety

Ensure credentials never appear in exceptions or debug output.

## Meta API Verification

Before implementing Ad endpoints, fields, statuses, filtering syntax, creative-related fields, or response structures, verify them against the **current official Meta Marketing API documentation**.

Do not invent:

* Ad fields
* endpoints
* statuses
* filtering parameters
* response structures
* Creative relationships

Use the API version supplied by MetaMetrics configuration.

## Separation of Responsibilities

Maintain this boundary:

```text
AdService
    → Ad retrieval and metadata

AdNormalizer
    → Raw Meta Ad → AdDTO

InsightsService
    → Ad performance

Historical layer
    → Historical delivery/relevance

AdSetService
    → Parent Ad Set operations

CampaignService
    → Campaign operations

HierarchyService
    → Campaign → Ad Sets → Ads

MetaClient
    → Meta API communication

Paginator
    → Pagination
```

Do not collapse these responsibilities into `AdService`.

## Design Principle

The Ad layer should answer:

```text
What Ad is this?

Which Ad Set does it belong to?

Which Campaign does it belong to?

What is its current Meta state?

What useful metadata belongs to it?
```

Combined with the Insights and Historical layers, MetaMetrics should additionally be able to answer:

```text
How did this Ad perform during a requested period?

Did this Ad actually deliver during that period?
```

Keep **entity metadata**, **hierarchy**, **current state**, and **historical performance** technically distinct.
