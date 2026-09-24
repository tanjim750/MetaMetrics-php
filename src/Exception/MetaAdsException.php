<?php

declare(strict_types=1);

namespace MetaMetrics\Exception;

use RuntimeException;

class MetaAdsException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $metaErrorCode = null,
        private readonly ?int $metaErrorSubcode = null,
        private readonly ?string $metaErrorType = null,
        private readonly ?string $traceId = null,
        ?\Throwable $previous = null,
    ) {
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

    /**
     * @return array{
     *     message: string,
     *     metaErrorCode: int|null,
     *     metaErrorSubcode: int|null,
     *     metaErrorType: string|null,
     *     traceId: string|null
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
     *     traceId: string|null
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
