<?php

declare(strict_types=1);

namespace MetaMetrics\Authentication;

use MetaMetrics\Client\Response;
use MetaMetrics\Exception\AuthenticationException;
use MetaMetrics\Exception\MetaAdsException;
use MetaMetrics\Exception\PermissionException;
use MetaMetrics\Exception\RateLimitException;
use MetaMetrics\Exception\ResourceNotFoundException;
use MetaMetrics\Exception\UnexpectedResponseException;

final class MetaErrorMapper
{
    /** @var list<int> */
    private const AUTHENTICATION_CODES = [102, 190];

    /** @var list<int> */
    private const PERMISSION_CODES = [10];

    /** @var list<int> */
    private const RATE_LIMIT_CODES = [4, 17, 32, 341, 613];

    /** @var list<int> */
    private const RESOURCE_CODES = [100, 803];

    public function map(Response $response): MetaAdsException
    {
        $error = $this->errorPayload($response);
        $code = $this->integerValue($error['code'] ?? null);
        $subcode = $this->integerValue($error['error_subcode'] ?? null);
        $type = $this->stringValue($error['type'] ?? null);
        $traceId = $this->stringValue($error['fbtrace_id'] ?? null);

        $context = [
            'metaErrorCode' => $code,
            'metaErrorSubcode' => $subcode,
            'metaErrorType' => $type,
            'traceId' => $traceId,
        ];

        if ($response->statusCode() === 429 || in_array($code, self::RATE_LIMIT_CODES, true)) {
            return new RateLimitException('Meta API rate limit exceeded.', ...$context);
        }

        if (in_array($code, self::AUTHENTICATION_CODES, true) || $response->statusCode() === 401) {
            return new AuthenticationException('Meta rejected the configured access token.', ...$context);
        }

        if ($this->isPermissionCode($code) || $response->statusCode() === 403) {
            return new PermissionException('The access token lacks permission to access the Meta Ad Account.', ...$context);
        }

        if (in_array($code, self::RESOURCE_CODES, true) || $response->statusCode() === 404) {
            return new ResourceNotFoundException('The configured Meta Ad Account could not be accessed.', ...$context);
        }

        if ($type === 'OAuthException') {
            return new AuthenticationException('Meta rejected the configured authentication.', ...$context);
        }

        return new UnexpectedResponseException('Meta returned an unexpected error response.', ...$context);
    }

    /** @return array<string, mixed> */
    private function errorPayload(Response $response): array
    {
        $body = $response->body();

        if (!is_array($body) || !isset($body['error']) || !is_array($body['error'])) {
            return [];
        }

        return $body['error'];
    }

    private function isPermissionCode(?int $code): bool
    {
        return in_array($code, self::PERMISSION_CODES, true)
            || ($code !== null && $code >= 200 && $code <= 299);
    }

    private function integerValue(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
