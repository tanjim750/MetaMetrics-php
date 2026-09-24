Implement the **Campaign layer** for MetaMetrics.

MetaMetrics should provide a clean, framework-independent abstraction for retrieving Meta Ads Campaign data without exposing Meta's raw Graph API structure to normal consumers.

The Campaign layer is responsible for Campaign metadata, Campaign retrieval, Campaign-specific queries, and integration with Campaign-level Insights.

## Core Responsibilities

Support:

* Retrieve Campaigns from the configured Ad Account
* Retrieve an individual Campaign
* Campaign ID
* Campaign Name
* Current Status
* Relevant creation/update metadata
* Campaign objective or other useful Campaign metadata when supported
* Campaign-level performance insights
* Date-range based Campaign data
* Filtering and field selection where appropriate
* Normalized Campaign output

Conceptually:

```text
Consumer
    ↓
Campaign Service
    ↓
Meta Client
    ↓
Meta Marketing API
    ↓
Campaign Normalizer
    ↓
Campaign DTO
```

## Campaign Service

Create a focused Campaign service using the existing Meta client abstraction.

It must not implement its own HTTP transport.

The service should provide clear operations for:

```text
Retrieve Campaign collection

Retrieve Campaign by ID

Retrieve Campaign-level insights

Retrieve Campaigns using supported filters/query options
```

Choose method names according to the project's existing naming conventions.

Do not create unnecessarily large methods or mix Campaign retrieval with unrelated Ad Set/Ad functionality.

## Campaign DTO

Represent normalized Campaign data using a dedicated immutable DTO/value object.

Core fields should include, where available:

```text
id
name
status
effectiveStatus
objective
createdTime
updatedTime
```

Only include fields that are useful and supported by the Meta Marketing API.

Do not blindly copy the entire Meta Campaign response into the DTO.

Keep Meta-specific raw response details outside the normalized domain object.

If Meta distinguishes configured status from effective status, preserve that distinction rather than merging them into a single ambiguous value.

## Campaign Normalization

Campaign API responses should pass through a Campaign normalizer before being exposed to normal consumers.

Conceptually:

```text
Raw Meta Campaign
        ↓
CampaignNormalizer
        ↓
CampaignDTO
```

The normalizer should:

* safely handle optional fields
* preserve IDs as strings
* normalize timestamps consistently
* avoid silently inventing missing values
* preserve meaningful distinctions between Meta fields
* fail predictably when essential response structure is malformed

Do not put HTTP logic inside the normalizer.

## Campaign Collection

Support retrieving Campaigns belonging to the configured Ad Account.

Example conceptual request:

```text
Ad Account
    ↓
Campaigns
    ├── Campaign A
    ├── Campaign B
    └── Campaign C
```

Pagination must use the shared pagination architecture.

CampaignService should not implement a second independent pagination system.

Consumers should not need to manually follow Meta paging cursors for normal Campaign retrieval.

## Individual Campaign

Support retrieving a Campaign using its Campaign ID.

Validate obvious local input errors such as an empty Campaign ID before making the request.

Do not attempt to infer whether the Campaign exists locally.

Meta remains the source of truth.

An invalid, inaccessible, or missing Campaign should be translated through the project's existing exception/error mapping.

## Current Status

Campaign current status must be represented accurately.

Do not interpret:

```text
ACTIVE
```

as proof that the Campaign delivered during an arbitrary historical date range.

Likewise:

```text
PAUSED
```

does not mean the Campaign had no historical delivery.

Current state and historical delivery are separate concepts.

## Historical Campaign Discovery

MetaMetrics must eventually support discovering which Campaigns actually delivered during a requested period.

Example:

```text
Requested Period:
September 1 → September 24
```

Campaign A:

```text
Created: August 15
Delivered: September 1–10
Current status: PAUSED
```

Campaign A is still historically relevant to the requested period.

Campaign B:

```text
Current status: ACTIVE
No delivery/performance during September 1–24
```

Its current `ACTIVE` status alone must not cause the library to claim that it delivered during that period.

Therefore:

```text
Current Campaign Status
        ≠
Historical Delivery
```

Do not implement historical discovery by filtering Campaign metadata using only:

```text
status
effective_status
created_time
updated_time
```

Historical relevance must be established from date-scoped delivery/performance evidence through the Insights/Historical layer.

CampaignService should integrate cleanly with that layer without duplicating its logic.

## Campaign Insights

Campaign-level analytics should use the shared Insights architecture.

Support requesting Campaign performance for a specific period.

Conceptually:

```text
Campaign
    +
Date Range
    +
Selected Metrics
        ↓
Campaign Insights
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

Do not implement separate metric parsing/calculation logic inside CampaignService.

Use the common:

```text
Insights parser
Action parser
Normalizer
Metric calculator
```

where applicable.

## Date Scoping

Campaign creation date must never determine the Insights reporting period.

Example:

```text
Campaign created:
August 10

Requested insights:
September 1 → September 24
```

Return only performance belonging to:

```text
September 1 → September 24
```

Do not automatically include August statistics simply because the Campaign existed during August.

The requested Insights date range is authoritative.

## Filtering

Campaign retrieval should integrate with the shared query/filter architecture.

Support only filters actually allowed by the Meta API and the current MetaMetrics query abstraction.

Potential concerns include:

```text
Campaign ID
Current status
Effective status
Selected fields
Other Meta-supported filters
```

Do not hardcode a large collection of Campaign filters directly inside CampaignService.

Keep filtering extensible.

## Field Selection

Avoid requesting every available Campaign field by default.

Define a sensible normalized field set required by MetaMetrics.

Allow the architecture to support explicit field selection where appropriate.

This reduces unnecessary response payload and keeps Meta API requests intentional.

## Relationship With Ad Sets

Maintain the Meta hierarchy:

```text
Ad Account
    ↓
Campaign
    ↓
Ad Set
    ↓
Ad
```

Campaign data should preserve enough identity information for other services to retrieve related Ad Sets.

However, CampaignService should not automatically fetch every Ad Set and Ad whenever a Campaign is retrieved.

That would create unnecessary API calls.

Full nested hierarchy retrieval belongs to the dedicated hierarchy layer.

## Raw Response

Normal Campaign operations should return normalized MetaMetrics data.

Do not expose raw Meta arrays as the primary result.

If the project provides raw-response/debug access, preserve that capability through the shared client architecture rather than embedding raw response fields inside `CampaignDTO`.

## Error Handling

Use the project's existing exception hierarchy.

Campaign operations should correctly propagate/map cases such as:

```text
Invalid authentication
Missing permission
Invalid/inaccessible Campaign
Invalid Ad Account
Rate limit
Network failure
Invalid input
Malformed Meta response
```

Do not create Campaign-specific versions of generic exceptions unless there is a concrete semantic reason.

Never expose access tokens in Campaign-related exceptions or logs.

## Performance

Avoid N+1 request patterns.

For example, retrieving 100 Campaigns should not automatically trigger:

```text
100 Campaign requests
+
100 Insights requests
+
100 Ad Set requests
```

unless the consumer explicitly requested those resources.

Prefer Meta-supported bulk/collection queries and level-based Insights requests where appropriate.

Keep metadata retrieval and analytics retrieval independently controllable.

## Tests

Add comprehensive unit tests using mocked/fake Meta API responses.

Do not require real Meta credentials.

Cover at minimum:

### Campaign Collection

Verify multiple Campaign responses are normalized correctly.

### Individual Campaign

Verify Campaign lookup by ID.

### Optional Fields

Verify missing optional Meta fields do not break normalization.

### Malformed Response

Verify malformed required Campaign data produces the appropriate predictable failure.

### Current Status

Verify current status/effective status are preserved without being interpreted as historical delivery.

### Pagination

Verify paginated Campaign responses use the shared paginator correctly.

### Date-Scoped Insights

Verify Campaign Insights requests preserve the exact requested date range.

### Historical Boundary

Verify the Campaign layer does not classify historical delivery solely from current status.

### Error Mapping

Cover:

```text
Authentication failure
Permission failure
Campaign not found/inaccessible
Rate limit
Network failure
```

### Credential Safety

Ensure access tokens never appear in Campaign exceptions or debug output.

## Important Meta API Rule

Before implementing Campaign fields, endpoints, filters, statuses, or request parameters, verify them against the **current official Meta Marketing API documentation**.

Do not invent:

* Campaign fields
* status values
* Graph API parameters
* filtering syntax
* endpoints
* response structures

Keep Meta-specific field definitions centralized where practical so API-version changes do not require modifications throughout the library.

## Separation of Responsibilities

Maintain this boundary:

```text
CampaignService
    → Campaign metadata/retrieval

CampaignNormalizer
    → Raw Campaign → normalized Campaign

InsightsService
    → Campaign performance

Historical layer
    → Was Campaign relevant/delivering during period?

HierarchyService
    → Campaign → Ad Sets → Ads

MetaClient
    → HTTP/API communication
```

Do not collapse these responsibilities into one Campaign class.

## Design Principle

The Campaign layer should answer:

```text
What Campaign is this?
What is its current Meta state?
What metadata belongs to it?
```

When combined with Insights/Historical services, MetaMetrics can additionally answer:

```text
How did this Campaign perform during a specific period?

Did this Campaign actually deliver during that period?
```

These are related questions, but they must remain technically distinct.
