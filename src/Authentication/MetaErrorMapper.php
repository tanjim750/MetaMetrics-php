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
    private const RESOURCE_CODES = [803];

    private const RESOURCE_SUBCODES = [33];

    private const TRANSIENT_CODES = [1, 2];

    public function __construct(private readonly MetaErrorParser $parser = new MetaErrorParser())
    {
    }

    public function map(
        Response $response,
        ?string $resourceType = null,
        ?string $resourceId = null,
    ): MetaAdsException
    {
        $error = $this->parser->parse($response);
        $code = $error->code();
        $subcode = $error->subcode();
        $type = $error->type();
        $httpStatus = $error->httpStatus();

        $context = [
            'metaErrorCode' => $code,
            'metaErrorSubcode' => $subcode,
            'metaErrorType' => $type,
            'traceId' => $error->traceId(),
            'httpStatus' => $httpStatus,
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
        ];

        if ($httpStatus === 429 || in_array($code, self::RATE_LIMIT_CODES, true)) {
            return new RateLimitException(
                'Meta API rate limit exceeded.',
                ...$context,
                retryable: true,
                retryAfterSeconds: $this->retryAfterSeconds($response),
                safeContext: $this->usageContext($response),
            );
        }

        if (in_array($code, self::AUTHENTICATION_CODES, true) || $httpStatus === 401) {
            return new AuthenticationException('Meta rejected the configured access token.', ...$context);
        }

        if ($this->isPermissionCode($code) || $httpStatus === 403) {
            return new PermissionException(
                'The access token does not have permission to perform this Meta API operation.',
                ...$context,
            );
        }

        if ($this->isResourceError($code, $subcode) || $httpStatus === 404) {
            return new ResourceNotFoundException(
                $resourceType === null
                    ? 'The requested Meta resource could not be found or accessed.'
                    : sprintf('The requested Meta %s could not be found or accessed.', $resourceType),
                ...$context,
            );
        }

        return new MetaAdsException(
            'Meta API request failed.',
            ...$context,
            retryable: $error->isTransient() === true
                || $httpStatus >= 500
                || in_array($code, self::TRANSIENT_CODES, true),
        );
    }

    private function isPermissionCode(?int $code): bool
    {
        return in_array($code, self::PERMISSION_CODES, true)
            || ($code !== null && $code >= 200 && $code <= 299);
    }

    private function isResourceError(?int $code, ?int $subcode): bool
    {
        return in_array($code, self::RESOURCE_CODES, true)
            || in_array($subcode, self::RESOURCE_SUBCODES, true);
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $retryAfter = $response->header('retry-after');

        if ($retryAfter === null || preg_match('/\A[0-9]+\z/D', $retryAfter) !== 1) {
            return null;
        }

        $seconds = filter_var($retryAfter, FILTER_VALIDATE_INT);

        return is_int($seconds) && $seconds >= 0 ? $seconds : null;
    }

    /** @return array<string, string> */
    private function usageContext(Response $response): array
    {
        $context = [];

        foreach (['x-app-usage', 'x-ad-account-usage', 'x-business-use-case-usage'] as $header) {
            $value = $response->header($header);

            if ($value !== null) {
                $context[str_replace('-', '_', $header)] = $value;
            }
        }

        return $context;
    }
}
