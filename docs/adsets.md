Implement the **Ad Set layer** for MetaMetrics.

MetaMetrics should provide a normalized, framework-independent abstraction for retrieving Meta Ads Ad Set data while preserving its relationship with the parent Campaign.

## Core Responsibilities

Support:

* Retrieve Ad Sets from the configured Ad Account
* Retrieve Ad Sets belonging to a specific Campaign
* Retrieve an individual Ad Set
* Ad Set ID
* Ad Set Name
* Current Status
* Effective Status where available
* Parent Campaign relationship
* Relevant Ad Set metadata
* Ad Set-level performance insights
* Date-range based analytics
* Filtering and field selection
* Normalized Ad Set output

Conceptually:

```text
Consumer
    ↓
AdSetService
    ↓
MetaClient
    ↓
Meta Marketing API
    ↓
AdSetNormalizer
    ↓
AdSetDTO
```

## AdSetService

Create a focused service for Ad Set operations.

Use the shared `MetaClient` for all Meta API communication.

The service should provide operations conceptually equivalent to:

```text
Retrieve all relevant Ad Sets

Retrieve Ad Sets by Campaign

Retrieve an individual Ad Set by ID

Retrieve Ad Set-level Insights
```

Follow the project's existing naming conventions when choosing actual method names.

Do not implement HTTP transport, authentication, pagination, metric parsing, or historical discovery directly inside `AdSetService`.

## AdSetDTO

Create an immutable normalized representation of an Ad Set.

Core fields should include, where supported and useful:

```text
id
name
campaignId
status
effectiveStatus
createdTime
updatedTime
```

Additional useful Ad Set metadata may be included only when supported by the current Meta Marketing API and relevant to the library.

For example, depending on the current API:

```text
optimizationGoal
billingEvent
dailyBudget
lifetimeBudget
startTime
endTime
```

Do not blindly expose every Meta Ad Set field.

Verify fields against the current official Meta Marketing API documentation before implementation.

## Parent Campaign Relationship

Every normalized Ad Set should preserve its parent Campaign relationship.

Conceptually:

```text
Campaign
    │
    ├── Ad Set A
    ├── Ad Set B
    └── Ad Set C
```

At minimum, preserve:

```text
campaignId
```

when Meta provides it.

Do not require the complete Campaign object to be loaded just to represent an Ad Set.

Avoid unnecessary nested API calls.

The relationship should be represented by identity rather than automatically fetching the parent resource.

## Ad Set Normalization

Raw Meta Ad Set responses should be normalized before being returned to normal consumers.

```text
Raw Meta Ad Set
        ↓
AdSetNormalizer
        ↓
AdSetDTO
```

The normalizer should:

* preserve IDs as strings
* preserve the Campaign ID
* safely handle optional fields
* normalize timestamps consistently
* preserve status distinctions
* avoid inventing missing values
* detect malformed essential response structures

Do not include HTTP or API request logic inside the normalizer.

## Ad Set Collection

Support retrieving Ad Sets belonging to the configured Ad Account.

Use Meta-supported collection requests rather than making one request per Ad Set.

Pagination must use the shared pagination system.

Consumers should not need to manually follow Meta paging cursors.

## Campaign-Specific Ad Sets

Support retrieving Ad Sets associated with a specific Campaign.

Conceptually:

```text
Campaign ID
    ↓
AdSetService
    ↓
Meta API
    ↓
Ad Sets belonging to Campaign
```

Validate obvious local errors such as an empty Campaign ID before making an API request.

Do not load every Campaign and filter locally when Meta provides an appropriate way to query the Campaign's Ad Sets.

## Individual Ad Set

Support retrieving an Ad Set by its ID.

Validate obvious local input errors such as an empty ID.

Meta should remain the source of truth for whether the resource actually exists and whether the configured token can access it.

Invalid or inaccessible resources should use the existing MetaMetrics exception architecture.

## Current Status vs Historical Delivery

Do not use current Ad Set status to determine whether it delivered during a historical period.

For example:

```text
Requested:
September 1 → September 24

Ad Set A
Delivered: September 1–12
Current Status: PAUSED
```

Ad Set A remains relevant to the requested historical period.

Likewise:

```text
Ad Set B
Current Status: ACTIVE
Delivery during requested period: none
```

Current `ACTIVE` status alone does not prove historical delivery.

Therefore:

```text
Current Status
      ≠
Historical Delivery
```

Keep this distinction explicit throughout the implementation.

## Historical Ad Set Discovery

Historical relevance should be determined through date-scoped delivery/performance evidence.

Do not infer historical activity solely from:

```text
status
effective_status
created_time
updated_time
start_time
end_time
```

These fields may provide metadata, but they are not sufficient proof of actual delivery.

The dedicated Historical/Insights layer should determine whether an Ad Set had relevant delivery/performance during the requested period.

Do not duplicate historical discovery logic inside `AdSetService`.

## Ad Set Insights

Ad Set-level analytics must use the shared Insights architecture.

Conceptually:

```text
Ad Set
    +
Date Range
    +
Selected Metrics
        ↓
Ad Set Insights
```

Metrics may include:

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

Do not create Ad Set-specific implementations of:

```text
Action parsing
Action value parsing
Metric calculation
Insights normalization
```

Reuse the shared Insights components.

## Date Scoping

The requested Insights period must remain independent from the Ad Set's creation/start date.

Example:

```text
Ad Set created:
August 15

Requested:
September 1 → September 24
```

Only statistics belonging to September 1–24 should be returned.

Do not include earlier performance simply because the Ad Set existed before the requested period.

## Filtering

Integrate with the shared query/filter abstraction.

Potential filtering concerns may include:

```text
Campaign
Ad Set ID
Status
Effective Status
Selected fields
Other Meta-supported filters
```

Only implement filters supported by the current Meta API and MetaMetrics query architecture.

Do not create an isolated Ad Set filtering system.

## Field Selection

Request only fields needed for the normalized result or explicitly requested by the consumer.

Avoid requesting every available Meta field by default.

Keep field definitions centralized where practical.

This makes future Meta API-version changes easier to maintain.

## Relationship With Ads

Preserve the hierarchy:

```text
Ad Account
    ↓
Campaign
    ↓
Ad Set
    ↓
Ad
```

An Ad Set should contain enough identity information for the Ads layer to retrieve its child Ads.

However:

```text
getAdSet()
```

must not automatically fetch all child Ads.

Likewise, retrieving an Ad Set should not automatically fetch its Campaign.

Full nested retrieval belongs to the hierarchy layer.

## Avoid N+1 Requests

Do not design flows where retrieving N Ad Sets automatically causes N additional Campaign, Insights, or Ads requests.

For example, avoid:

```text
100 Ad Sets
    ↓
100 Campaign requests
+
100 Insights requests
+
100 Ads requests
```

unless the consumer explicitly requested such data.

Prefer collection-level or Meta-supported level-based requests where possible.

## Raw Response

Normal Ad Set operations should return normalized MetaMetrics data.

Do not place the entire raw Meta response inside `AdSetDTO`.

If raw-response access exists for debugging, expose it through the shared client/debugging architecture.

## Error Handling

Use the existing MetaMetrics exception hierarchy.

Correctly handle:

```text
Authentication failure
Missing permission
Invalid/inaccessible Ad Account
Invalid/inaccessible Campaign
Invalid/inaccessible Ad Set
Invalid input
Rate limit
Network failure
Malformed Meta response
```

Do not create duplicate Ad Set-specific exceptions when existing generic exceptions already express the failure correctly.

Never expose credentials through errors or logs.

## Tests

Add unit tests using mocked/fake Meta responses.

Tests must not require real Meta credentials.

Cover at minimum:

### Ad Set Collection

Verify multiple Ad Sets are normalized correctly.

### Campaign-Specific Retrieval

Verify Ad Sets can be retrieved for a Campaign and maintain the correct `campaignId`.

### Individual Ad Set

Verify lookup by Ad Set ID.

### Parent Relationship

Verify the Campaign ID is preserved correctly.

### Optional Fields

Verify missing optional fields do not break normalization.

### Status

Verify current status and effective status are represented without being interpreted as historical delivery.

### Pagination

Verify shared pagination is used correctly.

### Date-Scoped Insights

Verify the exact requested period reaches the Insights layer.

### Historical Boundary

Verify `ACTIVE` or `PAUSED` alone does not determine historical relevance.

### Error Mapping

Test:

```text
Authentication failure
Permission failure
Campaign inaccessible
Ad Set inaccessible
Rate limit
Network failure
```

### Credential Safety

Ensure access tokens never appear in exceptions or debug output.

## Meta API Verification

Before implementing Ad Set endpoints, fields, statuses, filtering parameters, budget fields, optimization fields, or response structures, verify them against the **current official Meta Marketing API documentation**.

Do not invent Meta API behavior.

In particular, do not assume that a field available in one API version remains available or behaves identically in another.

Use the API version supplied by MetaMetrics configuration.

## Separation of Responsibilities

Maintain this boundary:

```text
AdSetService
    → Ad Set retrieval and metadata

AdSetNormalizer
    → Raw Meta Ad Set → normalized AdSetDTO

InsightsService
    → Ad Set performance

Historical layer
    → Historical delivery/relevance

AdService
    → Child Ads

HierarchyService
    → Campaign → Ad Sets → Ads

MetaClient
    → Meta API communication

Paginator
    → Pagination
```

Do not collapse these concerns into `AdSetService`.

## Design Principle

The Ad Set layer should primarily answer:

```text
What Ad Set is this?
Which Campaign does it belong to?
What is its current Meta state?
What useful metadata belongs to it?
```

Combined with the Insights and Historical layers, MetaMetrics can answer:

```text
How did this Ad Set perform during a requested period?

Did this Ad Set actually deliver during that period?
```

Preserve the distinction between **entity metadata**, **current state**, and **historical performance**.
