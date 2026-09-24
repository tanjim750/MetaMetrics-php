<?php

declare(strict_types=1);

namespace MetaMetrics\Client;

use CurlHandle;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\NetworkException;
use MetaMetrics\Support\DiagnosticSanitizer;
use RuntimeException;

final readonly class MetaClient implements MetaClientInterface
{
    private const BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private MetaConfig $config,
        private int $connectTimeoutSeconds = 10,
        private int $timeoutSeconds = 30,
        private JsonResponseDecoder $decoder = new JsonResponseDecoder(),
        private DiagnosticSanitizer $sanitizer = new DiagnosticSanitizer(),
    ) {
        if ($this->connectTimeoutSeconds < 1 || $this->timeoutSeconds < 1) {
            throw new InvalidInputException('HTTP timeouts must be positive integers.');
        }
    }

    public function send(Request $request): Response
    {
        $handle = curl_init();

        if (!$handle instanceof CurlHandle) {
            throw new NetworkException(
                'Unable to initialize the Meta API HTTP client.',
                retryable: true,
            );
        }

        $headers = [];
        $this->configure($handle, $request, $headers);
        $rawBody = curl_exec($handle);

        if ($rawBody === false) {
            $errorCode = curl_errno($handle);

            throw new NetworkException(
                'Meta API network request failed.',
                retryable: true,
                previous: new RuntimeException(sprintf('cURL transport error %d.', $errorCode)),
            );
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($statusCode === 0) {
            throw new NetworkException(
                'Meta API network request completed without an HTTP status.',
                retryable: true,
            );
        }

        return new Response(
            $statusCode,
            $this->decoder->decode($rawBody, $statusCode),
            $rawBody,
            $this->sanitizer->sanitizeHeaders($headers),
        );
    }

    /** @param array<string, string> $responseHeaders */
    private function configure(CurlHandle $handle, Request $request, array &$responseHeaders): void
    {
        $method = strtoupper($request->method());

        if ($method !== 'GET') {
            throw new InvalidInputException(sprintf('Unsupported Meta API HTTP method "%s".', $method));
        }

        $path = $request->path();

        if (!str_starts_with($path, '/') || str_contains($path, '?')) {
            throw new InvalidInputException('Meta API request path is invalid.');
        }

        $query = http_build_query($request->query(), '', '&', PHP_QUERY_RFC3986);
        $url = self::BASE_URL.$path.($query === '' ? '' : '?'.$query);

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer '.$this->config->accessToken(),
            ],
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $handle, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $separator = strpos($line, ':');

                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $value = trim(substr($line, $separator + 1));

                    if ($name !== '') {
                        $responseHeaders[$name] = $value;
                    }
                }

                return $length;
            },
            CURLOPT_USERAGENT => 'MetaMetrics/1.0',
        ]);
    }
}
