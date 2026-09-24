<?php

declare(strict_types=1);

namespace MetaMetrics\Client;

use JsonException;
use MetaMetrics\Exception\UnexpectedResponseException;

final class JsonResponseDecoder
{
    public function decode(string $rawBody, ?int $httpStatus = null): mixed
    {
        try {
            return json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedResponseException(
                'Meta returned a malformed JSON response.',
                httpStatus: $httpStatus,
                previous: $exception,
            );
        }
    }
}
