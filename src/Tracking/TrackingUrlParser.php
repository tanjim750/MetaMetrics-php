<?php

declare(strict_types=1);

namespace MetaMetrics\Tracking;

use MetaMetrics\Exception\InvalidInputException;

final class TrackingUrlParser
{
    private const DEFAULT_PARAMETER_MAPPING = [
        'campaignId' => 'utm_id',
        'adSetId' => 'utm_term',
        'adId' => 'utm_content',
        'source' => 'utm_source',
        'medium' => 'utm_medium',
    ];

    /**
     * Parses identifiers using the library's default Meta tracking convention.
     * `utm_term` and `utm_content` have no universal Ad Set or Ad semantics.
     *
     * @param array<string, string> $parameterMapping Partial overrides keyed by TrackingIdentifiers field name.
     */
    public function parse(string $url, array $parameterMapping = []): TrackingIdentifiers
    {
        $parts = $this->urlParts($url);
        $mapping = $this->parameterMapping($parameterMapping);
        $parameters = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $parameters);
        }

        $campaignId = $this->parameter($parameters, $mapping['campaignId']);

        if ($campaignId === null) {
            $fallback = $this->parameter($parameters, 'utm_campaign');
            $campaignId = $fallback !== null && preg_match('/\A[0-9]+\z/D', $fallback) === 1
                ? $fallback
                : null;
        }

        return new TrackingIdentifiers(
            campaignId: $campaignId,
            adSetId: $this->parameter($parameters, $mapping['adSetId']),
            adId: $this->parameter($parameters, $mapping['adId']),
            source: $this->parameter($parameters, $mapping['source']),
            medium: $this->parameter($parameters, $mapping['medium']),
        );
    }

    /** @return array<string, mixed> */
    private function urlParts(string $url): array
    {
        if (trim($url) === '') {
            throw new InvalidInputException('Tracking URL must not be empty.');
        }

        $parts = parse_url($url);

        if (filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || $parts['host'] === '') {
            throw new InvalidInputException('Tracking URL must be a valid HTTP or HTTPS URL.');
        }

        return $parts;
    }

    /**
     * @param array<string, string> $overrides
     * @return array{campaignId: string, adSetId: string, adId: string, source: string, medium: string}
     */
    private function parameterMapping(array $overrides): array
    {
        foreach ($overrides as $identifier => $parameter) {
            if (!is_string($identifier) || !array_key_exists($identifier, self::DEFAULT_PARAMETER_MAPPING)) {
                throw new InvalidInputException('Tracking parameter mapping contains an unsupported identifier.');
            }

            if (!is_string($parameter)
                || preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]*\z/D', $parameter) !== 1) {
                throw new InvalidInputException('Tracking parameter names must be non-empty URL parameter names.');
            }
        }

        $mapping = [...self::DEFAULT_PARAMETER_MAPPING, ...$overrides];

        if (count(array_unique($mapping)) !== count($mapping)) {
            throw new InvalidInputException('Tracking parameter mapping must use unique parameter names.');
        }

        return $mapping;
    }

    /** @param array<string, mixed> $parameters */
    private function parameter(array $parameters, string $name): ?string
    {
        $value = $parameters[$name] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
