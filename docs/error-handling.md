# MetaMetrics — Error Handling Implementation Instructions

Implement the **Error Handling architecture** for MetaMetrics.

The goal is to prevent Meta-specific HTTP/API error structures from leaking throughout the library while still preserving enough safe diagnostic information for consumers to understand and handle failures programmatically.

The consumer should receive stable MetaMetrics exceptions rather than needing to understand every raw Meta Graph API error payload.

---

## Implementation Status

The required implementation is complete in the following order:

1. **Stable exception contract** — all library exceptions extend `MetaAdsException`, which exposes safe HTTP, Meta, retry, resource, and diagnostic context.
2. **Central Meta error parsing and classification** — `MetaErrorParser` validates Meta error envelopes and `MetaErrorMapper` maps known evidence to stable exceptions.
3. **Transport and response handling** — `MetaClient` preserves safe response headers, wraps transport causes, and rejects malformed JSON through `JsonResponseDecoder`.
4. **Credential-safe diagnostics** — `DiagnosticSanitizer` redacts token-bearing URL parameters and messages and allowlists diagnostic headers.
5. **Service resource context** — authentication, campaign, Ad Set, Ad, and Insights lookups attach known resource identity without guessing it.
6. **Regression coverage** — tests cover hierarchy, classification, retry metadata, malformed responses, redaction, unknown errors, and mid-pagination failure.

Public exception metadata is available through:

```php
$exception->httpStatus();
$exception->metaErrorCode();
$exception->metaErrorSubcode();
$exception->metaErrorType();
$exception->traceId();
$exception->isRetryable();
$exception->retryAfterSeconds();
$exception->resourceType();
$exception->resourceId();
$exception->context();
```

Classification is deliberately conservative:

| Evidence | Exception |
|---|---|
| Meta code `102` or `190`, or HTTP `401` | `AuthenticationException` |
| Meta code `10`, codes `200`–`299`, or HTTP `403` | `PermissionException` |
| Meta code `4`, `17`, `32`, `341`, or `613`, or HTTP `429` | `RateLimitException` |
| Meta code `803`, subcode `33`, or HTTP `404` | `ResourceNotFoundException` |
| Unknown but valid Meta failure | `MetaAdsException` |
| Malformed JSON or malformed error envelope | `UnexpectedResponseException` |

An error type such as `OAuthException` is retained as diagnostic metadata but is not sufficient by itself to infer authentication failure. Unknown error combinations remain generic so newer Meta behavior does not get misclassified.

The implementation was checked against Meta's official generated Business SDK error model, which exposes HTTP status and headers together with Meta error code, subcode, type, trace ID, and transient status. See the official [`FacebookRequestError`](https://github.com/facebook/facebook-python-business-sdk/blob/main/facebook_business/exceptions.py) and [API response handling](https://github.com/facebook/facebook-python-business-sdk/blob/main/facebook_business/api.py). Numeric classification remains centralized in `MetaErrorMapper` so it can be revised as Meta's documented behavior changes.

---

# 1. Primary Goal

Build a consistent error pipeline:

```text
Meta API / HTTP / Transport Failure
              ↓
MetaClient
              ↓
Inspect HTTP + Meta Error Payload
              ↓
Classify Failure
              ↓
Map to MetaMetrics Exception
              ↓
Attach Safe Diagnostic Context
              ↓
Throw Stable Library Exception
```

The exception hierarchy should allow consumers to distinguish between:

```text
Authentication failure
Permission failure
Rate limit
Network failure
Invalid configuration
Invalid consumer input
Missing/inaccessible resource
Unsupported metric
Malformed/unexpected Meta response
Generic Meta/API failure
```

without parsing raw JSON themselves.

---

# 2. Exception Hierarchy

Use the existing exception structure.

Conceptually:

```text
MetaAdsException
│
├── AuthenticationException
├── PermissionException
├── RateLimitException
├── NetworkException
├── InvalidConfigurationException
├── InvalidInputException
├── ResourceNotFoundException
├── UnsupportedMetricException
└── UnexpectedResponseException
```

If implementation requires a generic API-specific exception in addition to these, introduce it only if it provides a clear semantic benefit and remains compatible with the existing architecture.

All library-specific exceptions should ultimately extend:

```text
MetaAdsException
```

so consumers can either catch specific failures:

```php
try {
    // MetaMetrics operation
} catch (RateLimitException $e) {
    // retry strategy
}
```

or catch all library failures:

```php
try {
    // MetaMetrics operation
} catch (MetaAdsException $e) {
    // generic MetaMetrics failure handling
}
```

---

# 3. Stable Library Contract

Meta's raw error representation may change between API versions.

The public exception taxonomy should remain significantly more stable.

Conceptually:

```text
Meta Error
   ↓
Internal classification
   ↓
Stable MetaMetrics exception
```

Do not make consumer applications depend directly on:

```text
Meta error code
Meta error subcode
Meta error type
raw Graph API JSON structure
```

for common failure handling.

Those values may be preserved as diagnostic metadata, but they should not replace semantic exception classes.

---

# 4. MetaAdsException

`MetaAdsException` is the root exception for MetaMetrics-specific failures.

It should support a safe diagnostic context where useful.

Potential information:

```text
message
previous exception

Meta error code
Meta error subcode
Meta error type
Meta trace ID

HTTP status
retryable indication
```

Do not require every exception to contain every field.

Do not store secrets.

The base exception should provide a consistent contract without becoming a dump of the complete HTTP request/response.

---

# 5. AuthenticationException

Use `AuthenticationException` when authentication credentials are rejected or no longer usable.

Examples may include:

```text
invalid access token
expired access token
revoked access token
malformed/unusable authentication credential
Meta authentication failure
```

Do not use it merely because:

```text
the requested Ad Account is inaccessible
```

if the token itself remains valid.

Authentication validity and resource authorization are separate concepts.

---

# 6. PermissionException

Use `PermissionException` when authentication succeeds but the token/user/system user lacks a required permission or authorization capability.

Examples:

```text
missing required Meta permission
insufficient permission for requested operation
token valid but not authorized for required capability
```

Keep this distinct from:

```text
AuthenticationException
```

because consumer recovery differs.

Authentication failure may require:

```text
new/renewed token
```

while permission failure may require:

```text
granting permissions
asset assignment
Business Manager configuration
app review/configuration
```

Do not collapse both into a generic "invalid token" error.

---

# 7. ResourceNotFoundException

Use this when the requested Meta resource genuinely cannot be found or is inaccessible in a way that semantically maps to a missing/unavailable resource.

Potential resources include:

```text
Ad Account
Campaign
Ad Set
Ad
```

Preserve useful safe context such as:

```text
resource type
resource ID
```

where appropriate.

Example message:

```text
Campaign "123..." could not be found or accessed.
```

Do not expose tokens.

Be careful:

```text
resource inaccessible
```

can sometimes be caused by permission problems.

Use Meta's actual error semantics when deciding whether the failure belongs to:

```text
PermissionException
```

or:

```text
ResourceNotFoundException
```

Do not guess solely from HTTP status.

---

# 8. InvalidConfigurationException

This exception is for invalid local MetaMetrics configuration.

Examples:

```text
missing access token
empty access token
invalid Ad Account ID format
invalid API version format
missing required configuration
```

These failures should normally be detected before a network request.

Do not use this exception for:

```text
token expired remotely
account inaccessible remotely
permission denied by Meta
```

Those are runtime/API failures.

---

# 9. InvalidInputException

Use for invalid consumer input unrelated to static library configuration.

Examples:

```text
invalid date range
since > until
empty required entity ID
invalid filter value
unsupported query combination
invalid pagination/query parameter
invalid level/scope combination
```

Fail early.

Do not make an API request when local validation already proves the query invalid.

---

# 10. UnsupportedMetricException

Use specifically when a consumer requests a metric MetaMetrics does not support.

Example:

```text
Consumer requests:
SOME_UNKNOWN_METRIC
```

Expected:

```text
UnsupportedMetricException
```

Do not:

```text
silently remove the metric
return null pretending it is supported
send arbitrary metric names directly to Meta
```

This exception should identify the unsupported normalized metric safely.

---

# 11. RateLimitException

Use when Meta indicates that the request cannot proceed because of rate limiting, throttling, or a relevant usage-limit condition.

The exception should preserve safe retry-related metadata when Meta provides it and the shared HTTP layer can interpret it reliably.

Potential context:

```text
HTTP status
Meta code
Meta subcode
retry-after information
usage-related headers/context
retryable = true
```

Do not invent retry times.

If Meta does not provide a reliable retry delay, do not fabricate one.

---

# 12. Rate Limiting Does Not Mean Automatic Infinite Retry

Throwing:

```text
RateLimitException
```

does not mean every service should immediately retry.

Do NOT implement:

```php
while (true) {
    tryAgain();
}
```

or arbitrary recursive retries.

Retry strategy should belong to a dedicated/shared retry policy if introduced.

The exception should make it possible for the consumer or shared retry layer to make an informed decision.

---

# 13. NetworkException

Use for transport-level failures where a valid Meta API response was not successfully obtained.

Examples:

```text
DNS failure
connection refused
connection timeout
TLS/transport failure
socket failure
HTTP client transport exception
```

Keep this separate from:

```text
Meta returned a valid HTTP response containing an API error
```

A Meta API error is not automatically a network error.

Preserve the underlying HTTP-client exception as:

```php
$previous
```

where useful.

---

# 14. UnexpectedResponseException

Use when Meta returns a response that cannot safely be interpreted according to the expected contract.

Examples:

```text
invalid JSON
unexpected top-level structure
missing structurally required data
malformed pagination
invalid metric value type
unexpected error payload
impossible response shape
```

Do not use this exception for a valid empty response.

For example:

```json
{
  "data": []
}
```

is valid for many collection/Insights requests.

It must NOT produce:

```text
UnexpectedResponseException
```

merely because no rows exist.

---

# 15. Empty Result Is Not an Error

This distinction must be enforced throughout the library.

Valid:

```json
{
  "data": []
}
```

may mean:

```text
No Campaigns
No Ad Sets
No Ads
No Insights
No historical delivery for requested period
```

Expected library behavior:

```text
empty normalized collection
```

not an exception.

Keep separate:

```text
successful request + zero results
```

from:

```text
request failure
```

---

# 16. Generic Meta API Errors

Meta may return an API failure that does not cleanly map to one of the specialized categories.

Do not incorrectly force every failure into:

```text
AuthenticationException
PermissionException
RateLimitException
ResourceNotFoundException
```

If the existing root exception is the intended generic fallback, use:

```text
MetaAdsException
```

with safe Meta diagnostic context.

Alternatively, if the project architecture benefits from a dedicated:

```text
ApiException
```

it may be introduced as a subclass of `MetaAdsException`.

Do not introduce it merely for naming symmetry.

Choose one consistent generic fallback strategy.

---

# 17. Error Classification Must Use More Than HTTP Status

Do not implement mappings such as:

```text
401 → AuthenticationException
403 → PermissionException
404 → ResourceNotFoundException
429 → RateLimitException
```

as the complete classifier.

HTTP status can be useful evidence, but Meta's API error payload may contain important semantic information such as:

```text
error.code
error.error_subcode
error.type
error.message
error_data
fbtrace_id
```

Use the documented combination of:

```text
HTTP status
+
Meta error code
+
Meta error subcode
+
error type/context
```

where applicable.

Verify exact mappings against the current official Meta Graph/Marketing API documentation.

---

# 18. Central Error Mapper

Create one centralized component or internal mechanism responsible for converting Meta API failures into MetaMetrics exceptions.

Conceptually:

```text
HTTP Response
      ↓
MetaErrorParser
      ↓
MetaError
      ↓
MetaErrorMapper
      ↓
MetaMetrics Exception
```

Exact class names may follow the project's architecture.

Do not duplicate code/subcode mapping inside:

```text
AccountService
CampaignService
AdSetService
AdService
InsightsService
HistoricalService
```

All services should receive consistent exception semantics from the shared client layer.

---

# 19. Parsed Meta Error Representation

If useful, introduce an internal immutable representation such as:

```text
MetaError

message
type
code
subcode
traceId
httpStatus
safe error data
```

This object should represent parsed Meta failure information before classification.

It must not expose the access token.

It should remain an internal transport/error concern rather than becoming part of every public service DTO.

---

# 20. Preserve Original Cause

When converting lower-level exceptions, preserve the original exception using PHP exception chaining.

Conceptually:

```php
throw new NetworkException(
    message: 'Unable to connect to Meta API.',
    previous: $httpClientException
);
```

This helps debugging while maintaining a clean public exception type.

Do not destroy useful root-cause information.

---

# 21. Safe Diagnostic Metadata

Where available, preserve useful Meta diagnostics such as:

```text
Meta error code
Meta error subcode
Meta error type
fbtrace_id
HTTP status
```

These can be valuable when investigating API problems.

However, diagnostic metadata must remain safe.

Never preserve secrets simply because they appeared somewhere in the request.

---

# 22. Never Expose Credentials

Exceptions, logs, stack-context arrays, debug output, and serialized exception metadata must never contain:

```text
access token
app secret
authorization header
secret query parameters
credentials
```

For example, never produce:

```text
Request failed:
https://graph.facebook.com/...?...&access_token=EAAB...
```

Instead sanitize:

```text
access_token=[REDACTED]
```

or omit it entirely.

---

# 23. URL Sanitization

If HTTP request URLs can appear in diagnostics, implement a reusable sanitizer.

Sensitive query parameters must be removed/redacted.

At minimum consider:

```text
access_token
appsecret_proof
```

and any future credential-bearing parameters.

Do not implement token redaction separately in every logger/exception.

---

# 24. Header Sanitization

Never log raw sensitive headers such as:

```text
Authorization
```

If request metadata is logged, sanitize headers centrally.

Prefer allowlisting safe diagnostic headers rather than logging every header and trying to blacklist secrets afterward.

---

# 25. Meta Error Message Safety

Meta's returned error message may be useful, but do not blindly assume every upstream string is safe to expose/log unchanged.

Before exposing upstream messages:

* ensure credentials are not embedded
* sanitize URLs if present
* avoid dumping complete request payloads
* preserve only useful diagnostic information

A safe Meta message may be included as context while the main exception message remains stable and understandable.

---

# 26. Consumer-Friendly Messages

Messages should describe what failed without requiring knowledge of Meta's internal error format.

Prefer:

```text
The Meta access token is invalid or expired.
```

over:

```text
OAuthException code 190.
```

The Meta code can remain diagnostic metadata.

Similarly:

```text
The access token does not have permission to perform this operation.
```

is preferable as the primary message.

Do not overpromise the exact cause if Meta's response is ambiguous.

---

# 27. Do Not Guess

If Meta returns an unknown error code/subcode, do not invent a semantic classification.

Fallback to the generic API/library exception while preserving safe diagnostics.

Wrong:

```text
unknown error
→ AuthenticationException because token might be bad
```

Correct:

```text
unknown/unmapped Meta error
→ generic Meta API failure
```

unless reliable evidence supports a more specific classification.

---

# 28. Error Mapping Must Be Version Maintainable

Meta error codes and behavior can evolve.

Keep mapping rules centralized and testable.

Do not scatter constants such as:

```php
190
200
...
```

through service classes.

If numeric mappings are required, define them in one error-classification layer with explanatory naming/documentation.

Verify current mappings against official documentation during implementation.

---

# 29. Retryability

Where useful, classify whether a failure is potentially retryable.

Examples that may be retryable depending on context:

```text
rate limit
temporary server failure
network timeout
transient upstream failure
```

Examples generally not solved by immediate retry:

```text
invalid configuration
unsupported metric
invalid date range
invalid token
missing permission
```

Do not automatically equate:

```text
retryable
```

with:

```text
retry immediately
```

Retryability is metadata/policy input.

---

# 30. Server Errors

Handle Meta/upstream server failures distinctly from malformed consumer input.

For example, a temporary Meta 5xx response should not become:

```text
InvalidInputException
```

Depending on the established architecture, map it to:

```text
generic Meta API exception
```

with retryable context where appropriate.

Do not fabricate successful empty data when Meta's server fails.

---

# 31. HTTP Success Does Not Automatically Mean Valid Response

A `2xx` response still needs structural validation.

For example:

```text
HTTP 200
```

with an invalid/unexpected body should not automatically be treated as successful normalized data.

Validate the expected response contract.

If the structure is malformed:

```text
UnexpectedResponseException
```

may be appropriate.

---

# 32. HTTP Error Does Not Automatically Define Semantics

Likewise:

```text
HTTP 400
```

can represent many different Meta API failures.

Do not map every 400 response to:

```text
InvalidInputException
```

Inspect the Meta error payload first.

A 400 response may contain an authentication, permission, resource, parameter, or other Graph API error.

---

# 33. Local Validation vs Remote Validation

Keep this distinction explicit.

### Local validation

Examples:

```text
empty token
invalid account ID format
malformed API version
since > until
unsupported metric
empty required entity ID
```

These should fail without HTTP requests.

### Remote validation

Examples:

```text
expired token
revoked token
missing Meta permission
account not accessible
Campaign no longer accessible
rate limit
```

These require Meta/API evidence.

Do not claim remote validity based only on local formatting.

---

# 34. Service Layer Behavior

Services such as:

```text
AccountService
CampaignService
AdSetService
AdService
InsightsService
HistoricalService
```

should generally not contain Meta error-code mapping.

They should:

```text
validate service-specific local input
        ↓
call MetaClient
        ↓
receive normalized success
or
receive typed MetaMetrics exception
```

This keeps service behavior predictable.

---

# 35. Pagination Failure

Pagination must not silently hide errors.

Example:

```text
Page 1 → success
Page 2 → success
Page 3 → rate limited
```

Do not return:

```text
pages 1 + 2
```

as if the full request succeeded.

Propagate:

```text
RateLimitException
```

unless an explicit partial-result feature is introduced later.

The default contract should prioritize correctness over incomplete data.

---

# 36. Partial Data

Do not silently return partial data after:

```text
network failure
rate limit
malformed page
API error
```

mid-operation.

If partial-result support is ever added, it must be explicit in the API and clearly mark:

```text
complete = false
```

or equivalent.

Do not introduce this implicitly.

---

# 37. Historical Empty Data vs Failure

HistoricalService must distinguish:

```text
No delivery during requested period
```

from:

```text
Unable to retrieve historical data
```

Example:

```json
{
  "data": []
}
```

with a valid successful response:

```text
empty HistoricalResult
```

A network failure:

```text
NetworkException
```

A permission failure:

```text
PermissionException
```

Do not turn failures into empty historical results.

---

# 38. Metric Errors

Metric calculation failures caused by valid zero denominators are NOT exceptions.

Example:

```text
Spend = 100
Purchases = 0
```

should result in:

```text
Cost Per Purchase = null
```

not:

```text
InvalidInputException
```

Similarly:

```text
Spend = 0
Purchase Value = 500
```

produces:

```text
ROAS = null
```

Undefined derived metrics are normal analytics states.

---

# 39. Malformed Metric Data

This is different:

```text
spend = "not-a-number"
```

when a numeric value is expected.

Do not silently convert this to:

```text
0
```

Depending on where malformed data is detected, surface an appropriate:

```text
UnexpectedResponseException
```

with safe diagnostic context.

---

# 40. Error Context

Where useful, exceptions may expose structured safe context.

Conceptually:

```php
[
    'http_status' => 400,
    'meta_code' => 190,
    'meta_subcode' => ...,
    'meta_type' => '...',
    'trace_id' => '...',
    'resource_type' => 'campaign',
    'resource_id' => '...',
    'retryable' => false,
]
```

Do not include:

```php
[
    'access_token' => '...',
    'authorization' => '...',
    'app_secret' => '...',
]
```

Structured context should be immutable/read-only where practical.

---

# 41. Exception Serialization

Be careful if exceptions support:

```text
toArray()
jsonSerialize()
```

or structured logging.

Such serialization must use sanitized diagnostic context.

Never serialize the original unsanitized HTTP request.

Avoid exposing nested HTTP-client objects that may contain headers or query parameters.

---

# 42. Logging Integration

Error handling should integrate cleanly with the optional logging layer.

The error layer may provide safe structured context.

The logger decides whether/how to record it.

Do not make exceptions directly write files or invoke framework loggers.

Keep:

```text
exception creation
```

separate from:

```text
logging side effects
```

---

# 43. NullLogger Compatibility

The entire error system must work correctly when no logger is configured.

Logging is optional.

Exceptions must never depend on a logger existing.

---

# 44. Framework Independence

Do not throw framework-specific exceptions such as:

```text
Laravel validation exceptions
Symfony HTTP exceptions
framework authorization exceptions
```

from the core library.

MetaMetrics exceptions must remain framework-independent.

Laravel/Symfony consumers can translate them at their application boundary.

---

# 45. HTTP Client Independence

Do not expose Guzzle/cURL/framework-specific transport exceptions as the primary public contract.

Wrap transport failures in:

```text
NetworkException
```

while preserving the original exception as `previous`.

This allows MetaMetrics to change HTTP implementation later without breaking consumer error handling.

---

# 46. Error Codes Internal to MetaMetrics

Do not introduce custom numeric error codes unless they solve a concrete consumer problem.

Typed exception classes are already the primary programmatic contract.

If internal error identifiers are introduced later, keep them stable and separate from Meta's own numeric codes.

Never overload:

```text
$exception->getCode()
```

with ambiguous mixtures of HTTP status, Meta error code, and internal library error code without a clearly defined contract.

---

# 47. Testing — Base Hierarchy

Verify every specific MetaMetrics exception extends:

```text
MetaAdsException
```

Consumers must be able to catch the root type.

---

# 48. Testing — Invalid Configuration

Examples:

```text
empty token
invalid Ad Account ID
malformed API version
```

Expected:

```text
InvalidConfigurationException
```

Verify no MetaClient request is performed.

---

# 49. Testing — Invalid Input

Examples:

```text
since > until
empty Campaign ID
invalid query combination
```

Expected:

```text
InvalidInputException
```

Verify failure occurs locally.

---

# 50. Testing — Unsupported Metric

Input:

```text
UNKNOWN_METRIC
```

Expected:

```text
UnsupportedMetricException
```

No unnecessary network request.

---

# 51. Testing — Authentication Failure

Use realistic mocked Meta error payloads representing invalid/expired/revoked authentication.

Expected:

```text
AuthenticationException
```

Verify safe Meta diagnostics are preserved.

Verify token is not present in:

```text
message
context
serialized exception
logs
```

---

# 52. Testing — Permission Failure

Use a mocked valid-authentication-but-insufficient-permission response.

Expected:

```text
PermissionException
```

Do not classify it as authentication failure.

---

# 53. Testing — Resource Failure

Mock an inaccessible/nonexistent Campaign, Ad Set, or Ad where Meta semantics support resource classification.

Expected:

```text
ResourceNotFoundException
```

Preserve resource identity safely where possible.

---

# 54. Testing — Rate Limit

Mock a documented Meta throttling/rate-limit response.

Expected:

```text
RateLimitException
```

Verify:

```text
retryable
```

metadata if supported.

Ensure no uncontrolled automatic retry occurs.

---

# 55. Testing — Network Failure

Make the HTTP transport throw a connection/timeout exception.

Expected:

```text
NetworkException
```

Verify original transport exception is preserved as:

```text
previous
```

---

# 56. Testing — Malformed JSON

Return malformed JSON from the mocked transport.

Expected:

```text
UnexpectedResponseException
```

Do not allow raw JSON parser exceptions to become the primary public contract.

---

# 57. Testing — Unexpected Structure

Examples:

```json
{
  "unexpected": "structure"
}
```

where a known collection structure is required.

Expected:

```text
UnexpectedResponseException
```

---

# 58. Testing — Valid Empty Data

Input:

```json
{
  "data": []
}
```

Expected:

```text
successful empty collection
```

No exception.

This test should exist for at least:

```text
Campaign retrieval
Insights
Historical discovery
```

where applicable.

---

# 59. Testing — Mid-Pagination Failure

Simulate:

```text
Page 1 success
Page 2 network failure
```

Expected:

```text
NetworkException
```

Do not return page 1 as a complete result.

Also test:

```text
Page 1 success
Page 2 rate limit
```

Expected:

```text
RateLimitException
```

---

# 60. Testing — Token Redaction

Use a fake secret token:

```text
EAAB_SUPER_SECRET_TEST_TOKEN
```

Force multiple failures.

Assert that this string never appears in:

```text
exception message
exception context
serialized exception
logger output
sanitized URL
```

This should be a dedicated security regression test.

---

# 61. Testing — URL Redaction

Input URL conceptually contains:

```text
?fields=id,name&access_token=SECRET
```

Sanitized diagnostic output must not contain:

```text
SECRET
```

It may become:

```text
?fields=id,name&access_token=[REDACTED]
```

or have the sensitive parameter removed.

---

# 62. Testing — Unknown Meta Error

Provide a valid Meta error envelope with an unknown/unmapped code.

Expected:

```text
generic Meta/API exception
```

Do not incorrectly classify it as authentication, permission, rate limit, or resource failure.

Preserve safe diagnostic values.

---

# 63. Testing — HTTP Status Is Not Sole Classifier

Create test cases where multiple Meta errors use the same HTTP status but represent different semantic categories.

Ensure classification uses Meta error information in addition to HTTP status.

This protects against simplistic:

```text
HTTP 400 → InvalidInputException
```

logic.

---

# 64. Testing — Undefined Metric Is Not Error

Input:

```text
Purchases = 0
Spend = 100
```

Expected:

```text
CPP = null
```

No exception.

Likewise test zero denominators for:

```text
ROAS
CPC
CTR
CPM
```

---

# 65. Testing — Malformed Numeric Metric

Input:

```text
spend = "abc"
```

Expected:

```text
UnexpectedResponseException
```

or the project's equivalent malformed-response exception.

Do not convert it to zero.

---

# 66. Official Meta Documentation Verification

Before implementing exact Meta error-code mappings, verify the current official Meta Graph API / Marketing API documentation for:

* OAuth/access-token errors
* permission errors
* Graph API error structure
* error codes
* error subcodes
* rate-limit errors
* usage/throttling headers
* temporary/server errors
* resource errors
* trace IDs
* retry-related information

Do not blindly copy error-code mappings from old Stack Overflow answers, old SDKs, blog posts, or outdated API versions.

Meta-specific mappings should remain centralized and easy to update.

---

# 67. Do Not Overfit to Current Development Account

The current development Ad Account may successfully return:

```json
{
  "data": []
}
```

because it contains no Campaigns or delivery data.

That is useful for validating empty-result behavior but does not exercise the complete error architecture.

Use mocked fixtures for:

```text
expired token
permission failure
rate limit
network failure
resource error
server failure
malformed response
```

Do not intentionally break real credentials just to test every error scenario.

---

# 68. Recommended Internal Flow

A request should conceptually behave as:

```text
Service
   ↓
Local Input Validation
   ↓
MetaClient
   ↓
HTTP Transport
   ↓
Transport failed?
   ├── yes
   │    ↓
   │ NetworkException
   │
   └── no
        ↓
Inspect HTTP Response
        ↓
Meta error envelope?
   ├── yes
   │    ↓
   │ Parse Meta Error
   │    ↓
   │ Classify
   │    ↓
   │ Typed MetaMetrics Exception
   │
   └── no
        ↓
Validate Success Structure
        ↓
Malformed?
   ├── yes → UnexpectedResponseException
   │
   └── no
        ↓
Normalized Success
```

---

# 69. Responsibility Map

Maintain the following boundaries:

```text
ConfigurationValidator
    → local configuration failures

Query / DateRange / Services
    → local consumer-input validation

HTTP Transport
    → raw network communication

MetaClient
    → request execution and response coordination

MetaErrorParser
    → parse Meta error payload

MetaErrorMapper
    → classify Meta errors

MetaAdsException hierarchy
    → stable consumer-facing failure contract

Logger
    → optional sanitized diagnostics
```

Do not place all responsibilities inside `MetaClient`.

---

# 70. Core Invariants

Preserve these invariants:

```text
Invalid token
≠
Missing permission
```

```text
Missing resource
≠
Empty collection
```

```text
Network failure
≠
Meta API error
```

```text
HTTP status
≠
complete error semantics
```

```text
Undefined derived metric
≠
exception
```

```text
No historical delivery
≠
historical request failure
```

```text
Partial pagination result
≠
successful complete result
```

```text
Diagnostic context
≠
raw credential dump
```

---

# 71. Final Design Principle

The consumer should be able to write:

```php
try {
    $results = $metaMetrics->insights()->get($query);
} catch (AuthenticationException $e) {
    // replace/renew authentication
} catch (PermissionException $e) {
    // inspect permissions or asset access
} catch (RateLimitException $e) {
    // defer/retry according to application policy
} catch (NetworkException $e) {
    // temporary transport handling
} catch (MetaAdsException $e) {
    // remaining MetaMetrics failures
}
```

without needing code such as:

```php
if ($response['error']['code'] === ...) {
    ...
}

if ($response['error']['error_subcode'] === ...) {
    ...
}
```

throughout the consuming application.

The final abstraction should be:

```text
Raw Meta / HTTP Failure
          ↓
Safe Parsing
          ↓
Semantic Classification
          ↓
Stable MetaMetrics Exception
          ↓
Consumer Recovery Strategy
```

Optimize for:

```text
correct classification
stable public behavior
safe diagnostics
credential protection
testability
framework independence
future Meta API compatibility
```

Do not hide failures, do not invent error semantics, and never sacrifice credential safety for debugging convenience.
