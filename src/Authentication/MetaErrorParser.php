<?php

declare(strict_types=1);

namespace MetaMetrics\Authentication;

use MetaMetrics\Client\Response;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Support\DiagnosticSanitizer;

final class MetaErrorParser
{
    public function __construct(private readonly DiagnosticSanitizer $sanitizer = new DiagnosticSanitizer())
    {
    }

    public function parse(Response $response): MetaError
    {
        $body = $response->body();
        $payload = [];

        if (is_array($body) && array_key_exists('error', $body)) {
            if (!is_array($body['error'])) {
                throw new UnexpectedResponseException(
                    'Meta returned a malformed error response.',
                    httpStatus: $response->statusCode(),
                );
            }

            $payload = $body['error'];
        }

        $traceId = $this->optionalString($payload, 'fbtrace_id', $response->statusCode());
        $traceHeader = $response->header('x-fb-trace-id');

        return new MetaError(
            httpStatus: $response->statusCode(),
            message: $this->safeMessage($payload, $response->statusCode()),
            type: $this->optionalString($payload, 'type', $response->statusCode()),
            code: $this->optionalInteger($payload, 'code', $response->statusCode()),
            subcode: $this->optionalInteger($payload, 'error_subcode', $response->statusCode()),
            traceId: $traceId ?? ($traceHeader !== null && $traceHeader !== '' ? $traceHeader : null),
            transient: $this->optionalBoolean($payload, 'is_transient', $response->statusCode()),
        );
    }

    /** @param array<string, mixed> $payload */
    private function safeMessage(array $payload, int $httpStatus): ?string
    {
        $message = $this->optionalString($payload, 'message', $httpStatus);

        return $message === null ? null : $this->sanitizer->sanitizeMessage($message);
    }

    /** @param array<string, mixed> $payload */
    private function optionalString(array $payload, string $field, int $httpStatus): ?string
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null) {
            return null;
        }

        if (!is_string($payload[$field]) || $payload[$field] === '') {
            throw new UnexpectedResponseException(
                'Meta returned malformed error diagnostics.',
                httpStatus: $httpStatus,
            );
        }

        return $payload[$field];
    }

    /** @param array<string, mixed> $payload */
    private function optionalInteger(array $payload, string $field, int $httpStatus): ?int
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null) {
            return null;
        }

        if (!is_int($payload[$field])) {
            throw new UnexpectedResponseException(
                'Meta returned malformed error diagnostics.',
                httpStatus: $httpStatus,
            );
        }

        return $payload[$field];
    }

    /** @param array<string, mixed> $payload */
    private function optionalBoolean(array $payload, string $field, int $httpStatus): ?bool
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null) {
            return null;
        }

        if (!is_bool($payload[$field])) {
            throw new UnexpectedResponseException(
                'Meta returned malformed error diagnostics.',
                httpStatus: $httpStatus,
            );
        }

        return $payload[$field];
    }
}
