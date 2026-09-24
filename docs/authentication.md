Implement the **authentication layer** for MetaMetrics.

MetaMetrics does not provide a user login system and does not generate or manage Meta access tokens. A consumer application supplies a Meta access token through the configuration layer.

The authentication layer is responsible for using that configuration to verify that Meta API communication is possible and for translating authentication-related Meta API failures into predictable library behavior.

## Core Responsibilities

The authentication layer should support:

* Access token validation
* Meta API connection validation
* Ad Account accessibility validation
* Authentication error detection
* Expired/invalid token detection
* Permission-related error detection
* Safe handling of authentication failures

Keep authentication concerns separate from configuration validation.

Conceptually:

```text
Consumer Application
        ↓
MetaConfig
        ↓
Authentication / Connection Validation
        ↓
Meta Marketing API
        ↓
Validation Result or Typed Exception
```

## Access Token Ownership

MetaMetrics receives an already-issued Meta access token.

Do not implement:

* Facebook Login
* OAuth authorization UI
* OAuth redirect/callback handling
* Access-token generation
* browser-based authentication
* application login/session management

These concerns belong to the consumer application or Meta's authentication flow.

MetaMetrics should simply use the supplied access token when communicating with the Marketing API.

## Authentication Service

Create a focused authentication component responsible for validating the configured Meta connection.

Prefer a service abstraction such as:

```php
AuthenticationService
```

or the closest naming convention already established in the project.

Do not unnecessarily duplicate HTTP communication logic.

Actual Meta requests should go through the existing Meta client abstraction.

The authentication service should depend on abstractions where possible rather than creating its own HTTP client.

## Connection Validation

Provide a way to determine whether the configured credentials can successfully communicate with Meta.

Connection validation should perform a lightweight Meta API request.

Avoid fetching large datasets or Insights merely to validate authentication.

The validation request should prove enough to determine whether:

* the token can authenticate
* Meta API is reachable
* the configured Ad Account can be accessed

Use the current official Meta Marketing API behavior when selecting the appropriate validation request.

Do not rely on undocumented endpoints.

## Token Validation

Differentiate between:

```text
Token exists locally
```

and:

```text
Token is accepted by Meta
```

The configuration layer only checks that a token was supplied.

The authentication layer determines whether Meta accepts it.

Handle cases such as:

* invalid token
* expired token
* revoked token
* malformed/unsupported authentication
* other authentication-related Meta errors

Do not attempt to infer token validity from token format or length.

Remote validation is the source of truth.

## Ad Account Validation

The configured Ad Account should be validated against Meta.

Successful token authentication does not necessarily mean the token has access to the configured Ad Account.

The authentication flow should therefore be capable of distinguishing:

```text
Valid Token + Accessible Account
```

from:

```text
Valid Token + Inaccessible/Invalid Account
```

Do not treat these as the same failure.

## Permission Errors

Meta may accept the token but reject an operation because the required permission is missing.

Detect permission-related API errors and map them to the project's permission-specific exception when possible.

For example:

```php
PermissionException
```

Do not determine permissions by hardcoding assumptions about the consumer's token.

Use Meta's actual API response/error information.

## Exception Mapping

Authentication-related Meta errors should be translated into MetaMetrics exceptions.

Use existing project exceptions where available.

Expected conceptual mapping:

```text
Invalid / Expired / Revoked Token
        ↓
AuthenticationException

Missing Required Permission
        ↓
PermissionException

Invalid / Inaccessible Ad Account
        ↓
ResourceNotFoundException
or the existing account-specific exception

Network Failure
        ↓
NetworkException

Rate Limit
        ↓
RateLimitException

Unexpected Meta Response
        ↓
UnexpectedResponseException
```

Before implementing mappings, inspect the project's existing exception hierarchy and reuse it.

Do not create duplicate exception types when an appropriate one already exists.

Preserve useful Meta error context where safe, such as:

* Meta error code
* error subcode
* error type
* request/trace identifier

Never expose the access token or other secrets.

## Validation Result

For successful validation, provide a small predictable result.

It may contain useful non-sensitive information such as:

```text
authenticated: true
accountAccessible: true
accountId
```

If the project already follows DTO/value-object patterns, use the same approach.

Do not return the raw Meta response as the primary authentication result.

Raw response access may remain available through the lower-level client/debugging layer if already supported.

## Security

Authentication code must treat credentials as secrets.

Never include the access token in:

* exception messages
* logs
* validation result objects
* URLs exposed to logs
* debug output
* serialized DTOs
* request diagnostics

If HTTP request logging exists, redact sensitive query parameters and authorization data.

Be especially careful because Meta Graph API requests may carry the access token as a request parameter depending on the client implementation.

## Error Information

Do not discard useful Meta error information.

When safe, retain structured metadata that helps the consumer understand failures.

For example:

```text
Library Exception
    ├── message
    ├── Meta error code
    ├── Meta error subcode
    ├── Meta error type
    └── trace/request identifier
```

The access token must never be included.

Avoid depending only on human-readable Meta error messages for classification when structured error codes/subcodes are available.

## API Version

Authentication requests must use the API version supplied by `MetaConfig`.

Do not bypass configuration by hardcoding a separate API version inside the authentication layer.

Conceptually:

```text
MetaConfig.apiVersion
        ↓
MetaClient
        ↓
/{configured-version}/...
```

## Separation of Concerns

Do not put authentication logic inside:

```text
CampaignService
AdSetService
AdService
InsightsService
HistoricalService
```

Those services should rely on the shared Meta client and common error handling.

Likewise, the authentication layer must not contain:

* Campaign retrieval logic
* Insights parsing
* pagination
* historical delivery discovery
* advertising metric calculations

## Tests

Add unit tests covering the authentication behavior using mocked/fake Meta client responses.

Tests must not require real Meta credentials.

At minimum cover:

### Successful Validation

```text
Valid Token
+
Accessible Ad Account
        ↓
Successful validation
```

### Invalid Token

Simulate Meta returning an authentication error and verify that it becomes the correct MetaMetrics authentication exception.

### Expired Token

Verify expired-token responses are classified correctly.

### Missing Permission

Simulate a permission-related Meta response and verify that it becomes the permission-specific exception.

### Invalid/Inaccessible Ad Account

Verify that account-access failures are not incorrectly classified as invalid-token failures.

### Network Failure

Verify network errors are mapped through the existing network exception architecture.

### Rate Limit

Verify rate-limit errors remain distinguishable from authentication failures.

### Credential Safety

Use a known fake token in tests and verify that it never appears in:

```text
exception messages
validation results
logs, if logging is involved
```

## Important

Before implementing Meta-specific error-code mappings or choosing the validation endpoint, verify them against the **current official Meta Marketing API documentation**.

Do not invent Meta error codes, permission names, endpoints, or response structures.

Keep Meta-specific interpretation centralized so future API-version changes do not require modifications throughout the library.

## Design Principle

The authentication layer should remain focused:

```text
Configured Credentials
        ↓
Meta API Request
        ↓
Authenticate
        ↓
Verify Account Access
        ↓
Interpret Meta Authentication Errors
        ↓
Success Result / Typed Exception
```

It should answer:

**"Can MetaMetrics safely communicate with this Meta Ad Account using the supplied credentials?"**

Everything beyond that belongs to the appropriate MetaMetrics service.
