<?php

declare(strict_types=1);

namespace MetaMetrics\Support;

final class DiagnosticSanitizer
{
    private const SENSITIVE_QUERY_PARAMETERS = ['access_token', 'appsecret_proof'];
    private const SAFE_HEADERS = [
        'retry-after',
        'x-ad-account-usage',
        'x-app-usage',
        'x-business-use-case-usage',
        'x-fb-trace-id',
    ];

    public function sanitizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return '[INVALID URL]';
        }

        parse_str($parts['query'] ?? '', $query);

        foreach ($query as $name => $value) {
            if (is_string($name) && in_array(strtolower($name), self::SENSITIVE_QUERY_PARAMETERS, true)) {
                $query[$name] = '[REDACTED]';
            }
        }

        $authority = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $sanitized = $parts['scheme'].'://'.$authority.($parts['path'] ?? '');

        if ($query !== []) {
            $sanitized .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $sanitized;
    }

    public function sanitizeMessage(string $message): string
    {
        $message = preg_replace(
            '~(["\']?(?:access_token|appsecret_proof|app_secret|authorization)["\']?\s*:\s*)(["\'])[^"\']*\2~i',
            '$1$2[REDACTED]$2',
            $message,
        ) ?? '[REDACTED]';

        $message = preg_replace(
            '/\bAuthorization\s*[:=]\s*(?:(?:Bearer|Basic)\s+)?[^\s,;"\']+/i',
            'Authorization: [REDACTED]',
            $message,
        ) ?? '[REDACTED]';

        $message = preg_replace(
            '/\b(access_token|appsecret_proof|app_secret)=([^&\s]+)/i',
            '$1=[REDACTED]',
            $message,
        ) ?? '[REDACTED]';

        $message = preg_replace(
            '/\b(access_token|appsecret_proof|app_secret)\s*:\s*([^\s,;}]+)/i',
            '$1[REDACTED]',
            $message,
        ) ?? '[REDACTED]';

        $message = preg_replace(
            '/\b(access_token|appsecret_proof|app_secret|authorization)%3D([^&\s]+)/i',
            '$1%3D[REDACTED]',
            $message,
        ) ?? '[REDACTED]';

        return preg_replace(
            '/\bBearer\s+[^\s]+/i',
            'Bearer [REDACTED]',
            $message,
        ) ?? '[REDACTED]';
    }

    /** @param array<string, string> $headers @return array<string, string> */
    public function sanitizeHeaders(array $headers): array
    {
        $safe = [];

        foreach ($headers as $name => $value) {
            $normalized = strtolower($name);

            if (in_array($normalized, self::SAFE_HEADERS, true)) {
                $safe[$normalized] = $value;
            }
        }

        return $safe;
    }
}
