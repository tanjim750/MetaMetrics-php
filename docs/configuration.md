Implement the **configuration layer** for MetaMetrics.

MetaMetrics is a framework-independent PHP library for communicating with the Meta Marketing API. The configuration layer should provide a clean, immutable, validated representation of the credentials and API settings required by the rest of the library.

## Configuration Requirements

The configuration must support:

* Meta Access Token
* Meta Ad Account ID
* Meta Marketing API Version

These are the core configuration values required by the library.

Do not read configuration directly from `.env`, `$_ENV`, Laravel config, Symfony config, or any framework-specific source.

The consumer application is responsible for loading configuration from its preferred source and passing the values to MetaMetrics.

Example concept:

```php
$config = new MetaConfig(
    accessToken: $accessToken,
    adAccountId: $adAccountId,
    apiVersion: $apiVersion,
);
```

## MetaConfig

Create an immutable configuration object representing the Meta Marketing API configuration.

It should contain:

```text
accessToken
adAccountId
apiVersion
```

Use strict typing.

The configuration values should not be publicly mutable after construction.

Provide appropriate read-only access to the values.

Prefer modern PHP features where appropriate, such as constructor property promotion and `readonly`, depending on the PHP version defined by the project.

## Access Token

The access token:

* must be a string
* must not be empty
* should be preserved exactly as supplied
* must never be automatically logged or exposed in exception messages
* should not be modified, trimmed into a different token, masked internally, or transformed

Whitespace-only input should be considered invalid.

Do not attempt to determine whether the token is actually valid with Meta during local configuration validation.

Remote token validation belongs to the API/authentication layer.

## Ad Account ID

The Ad Account ID:

* must be a string
* must not be empty
* should accept the standard Meta Ad Account identifier format

Canonical internal format should be:

```text
act_123456789
```

For developer ergonomics, also accept a numeric account ID:

```text
123456789
```

and normalize it internally to:

```text
act_123456789
```

After normalization, validate that the value follows:

```text
act_<numeric-id>
```

Reject malformed values such as:

```text
act_
act_abc
abc123
act_123_456
```

Do not perform a network request to determine whether the account actually exists.

## API Version

Accept Meta Marketing API versions in canonical format:

```text
vXX.X
```

Examples:

```text
v23.0
v24.0
```

Validation should reject malformed values such as:

```text
23
23.0
version23
v23
v23.x
```

Do not hardcode business logic around a particular Meta API version.

The configuration layer should only validate the format.

Whether a particular version is currently supported by Meta is a remote/API concern and may change over time.

## Configuration Validation

Keep validation separate from the configuration object's responsibility where practical.

`ConfigurationValidator` should validate configuration rules such as:

```text
Access Token
    → required
    → non-empty

Ad Account ID
    → required
    → valid canonical format after normalization

API Version
    → required
    → valid vXX.X format
```

Validation must be deterministic and require no network requests.

Do not mix configuration validation with:

* token validity checks
* Meta permissions checks
* Ad Account existence checks
* API connectivity checks
* rate-limit checks

Those belong to other layers.

## Validation Failure

Invalid configuration should fail early.

Use the project's configuration-specific exception, such as:

```php
InvalidConfigurationException
```

if that exception already exists.

Error messages should clearly identify the invalid configuration field.

For example:

```text
Meta Ad Account ID must follow the format "act_<numeric-id>".
```

Never include the actual access token in an exception.

## Security

Treat the access token as sensitive data.

The configuration layer must never expose credentials through:

```text
__toString()
debug output
exception messages
automatic serialization intended for logs
```

Avoid implementing methods such as:

```php
toArray()
jsonSerialize()
```

that could accidentally expose the access token unless there is a concrete library requirement for them.

If debug representation is required later, sensitive values must be redacted.

## Framework Independence

Do not add dependencies on:

```text
Laravel
Symfony configuration components
dotenv
global environment variables
framework containers
```

The configuration layer should work simply through PHP objects.

This must remain valid:

```php
$config = new MetaConfig(
    accessToken: '...',
    adAccountId: 'act_123456789',
    apiVersion: 'vXX.X',
);
```

## Tests

Add comprehensive unit tests for configuration behavior.

At minimum cover:

**Valid configuration**

```text
valid token
valid act_ account ID
valid API version
```

**Account normalization**

```text
123456789
→
act_123456789
```

**Invalid access token**

```text
''
'   '
```

**Invalid Ad Account IDs**

```text
''
'act_'
'act_abc'
'abc123'
```

**Invalid API versions**

```text
''
'23.0'
'v23'
'version23'
'v23.x'
```

Also verify that sensitive access-token values do not appear in validation exception messages.

## Design Principle

Keep this component small.

Its responsibility is only:

```text
Receive configuration
        ↓
Normalize safe configuration values
        ↓
Validate local configuration rules
        ↓
Expose immutable configuration
```

It must **not** communicate with Meta.

Remote authentication, token validation, permission validation, and connection validation will be handled separately by the Meta API client/authentication layer.
