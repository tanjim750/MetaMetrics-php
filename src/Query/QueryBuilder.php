<?php

declare(strict_types=1);

namespace MetaMetrics\Query;

use JsonException;
use MetaMetrics\Exception\InvalidInputException;

final class QueryBuilder
{
    /**
     * @param list<Filter> $filters
     * @param list<string> $allowedFields
     */
    public function encodeFilters(array $filters, array $allowedFields): ?string
    {
        if ($filters === []) {
            return null;
        }

        $serialized = [];

        foreach ($filters as $filter) {
            if (!$filter instanceof Filter) {
                throw new InvalidInputException('Insights filters must be Filter instances.');
            }

            if (!in_array($filter->field(), $allowedFields, true)) {
                throw new InvalidInputException(sprintf('Unsupported Insights filter field "%s".', $filter->field()));
            }

            $serialized[] = $filter->toArray();
        }

        try {
            return json_encode($serialized, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidInputException('Insights filters could not be encoded.');
        }
    }
}
