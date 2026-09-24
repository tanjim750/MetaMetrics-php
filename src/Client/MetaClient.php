<?php

declare(strict_types=1);

namespace MetaMetrics\Client;

use CurlHandle;
use JsonException;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\NetworkException;
use MetaMetrics\Exception\UnexpectedResponseException;

final readonly class MetaClient implements MetaClientInterface
{
    private const BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private MetaConfig $config,
        private int $connectTimeoutSeconds = 10,
        private int $timeoutSeconds = 30,
    ) {
        if ($this->connectTimeoutSeconds < 1 || $this->timeoutSeconds < 1) {
            throw new \InvalidArgumentException('HTTP timeouts must be positive integers.');
        }
    }

    public function send(Request $request): Response
    {
        $handle = curl_init();

        if (!$handle instanceof CurlHandle) {
            throw new NetworkException('Unable to initialize the Meta API HTTP client.');
        }

        $this->configure($handle, $request);
        $rawBody = curl_exec($handle);

        if ($rawBody === false) {
            $errorCode = curl_errno($handle);

            throw new NetworkException(sprintf('Meta API network request failed (cURL error %d).', $errorCode));
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($statusCode === 0) {
            throw new NetworkException('Meta API network request completed without an HTTP status.');
        }

        return new Response($statusCode, $this->decodeBody($rawBody));
    }

    private function configure(CurlHandle $handle, Request $request): void
    {
        $method = strtoupper($request->method());

        if ($method !== 'GET') {
            throw new UnexpectedResponseException(sprintf('Unsupported Meta API HTTP method "%s".', $method));
        }

        $path = $request->path();

        if (!str_starts_with($path, '/') || str_contains($path, '?')) {
            throw new UnexpectedResponseException('Meta API request path is invalid.');
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
            CURLOPT_USERAGENT => 'MetaMetrics/1.0',
        ]);
    }

    private function decodeBody(string $rawBody): mixed
    {
        try {
            return json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $rawBody;
        }
    }
}
