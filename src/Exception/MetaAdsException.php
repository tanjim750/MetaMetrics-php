<?php

declare(strict_types=1);

namespace MetaMetrics\Exception;

use RuntimeException;

class MetaAdsException extends RuntimeException
{
    private const SAFE_CONTEXT_KEYS = [
        'x_ad_account_usage',
        'x_app_usage',
        'x_business_use_case_usage',
    ];

    /** @var array<string, bool|float|int|string|null> */
    private readonly array $safeContext;

    /**
     * @param array<string, bool|float|int|string|null> $safeContext
     */
    public function __construct(
        string $message,
        private readonly ?int $metaErrorCode = null,
        private readonly ?int $metaErrorSubcode = null,
        private readonly ?string $metaErrorType = null,
        private readonly ?string $traceId = null,
        private readonly ?int $httpStatus = null,
        private readonly bool $retryable = false,
        private readonly ?int $retryAfterSeconds = null,
        private readonly ?string $resourceType = null,
        private readonly ?string $resourceId = null,
        array $safeContext = [],
        ?\Throwable $previous = null,
    ) {
        foreach ($safeContext as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                throw new \InvalidArgumentException('Exception context must contain safe scalar values.');
            }

            if (preg_match('/token|secret|authorization|appsecret|credential/i', $key) === 1) {
                throw new \InvalidArgumentException('Exception context contains a sensitive key.');
            }

            if (!in_array($key, self::SAFE_CONTEXT_KEYS, true)) {
                throw new \InvalidArgumentException('Exception context contains an unsupported key.');
            }
        }

        $this->safeContext = $safeContext;
        parent::__construct($message, 0, $previous);
    }

    public function metaErrorCode(): ?int
    {
        return $this->metaErrorCode;
    }

    public function metaErrorSubcode(): ?int
    {
        return $this->metaErrorSubcode;
    }

    public function metaErrorType(): ?string
    {
        return $this->metaErrorType;
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function resourceType(): ?string
    {
        return $this->resourceType;
    }

    public function resourceId(): ?string
    {
        return $this->resourceId;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function context(): array
    {
        return array_filter([
            ...$this->safeContext,
            'http_status' => $this->httpStatus,
            'meta_code' => $this->metaErrorCode,
            'meta_subcode' => $this->metaErrorSubcode,
            'meta_type' => $this->metaErrorType,
            'trace_id' => $this->traceId,
            'retryable' => $this->retryable,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{
     *     message: string,
     *     metaErrorCode: int|null,
     *     metaErrorSubcode: int|null,
     *     metaErrorType: string|null,
     *     traceId: string|null,
     *     httpStatus: int|null,
     *     retryable: bool,
     *     retryAfterSeconds: int|null,
     *     resourceType: string|null,
     *     resourceId: string|null,
     *     context: array<string, bool|float|int|string|null>
     * }
     */
    public function __debugInfo(): array
    {
        return [
            'message' => $this->getMessage(),
            'metaErrorCode' => $this->metaErrorCode,
            'metaErrorSubcode' => $this->metaErrorSubcode,
            'metaErrorType' => $this->metaErrorType,
            'traceId' => $this->traceId,
            'httpStatus' => $this->httpStatus,
            'retryable' => $this->retryable,
            'retryAfterSeconds' => $this->retryAfterSeconds,
            'resourceType' => $this->resourceType,
            'resourceId' => $this->resourceId,
            'context' => $this->safeContext,
        ];
    }

    /**
     * Excludes stack arguments because upstream responses may contain secrets.
     *
     * @return array{
     *     message: string,
     *     metaErrorCode: int|null,
     *     metaErrorSubcode: int|null,
     *     metaErrorType: string|null,
     *     traceId: string|null,
     *     httpStatus: int|null,
     *     retryable: bool,
     *     retryAfterSeconds: int|null,
     *     resourceType: string|null,
     *     resourceId: string|null,
     *     context: array<string, bool|float|int|string|null>
     * }
     */
    public function __serialize(): array
    {
        return $this->__debugInfo();
    }

    /** @param array<string, mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('MetaMetrics exceptions cannot be unserialized.');
    }
}
