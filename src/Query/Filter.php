<?php

declare(strict_types=1);

namespace MetaMetrics\Query;

use MetaMetrics\Exception\InvalidInputException;

final readonly class Filter
{
    /** @param scalar|list<scalar> $value */
    public function __construct(
        private string $field,
        private FilterOperator $operator,
        private string|int|float|bool|array $value,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)?\z/D', $this->field) !== 1) {
            throw new InvalidInputException('Filter field must use a valid Meta field name.');
        }

        if (is_array($this->value) && !array_is_list($this->value)) {
            throw new InvalidInputException('Filter array values must be lists.');
        }

        if (is_array($this->value)) {
            foreach ($this->value as $value) {
                if (!is_scalar($value)) {
                    throw new InvalidInputException('Filter list values must be scalar.');
                }
            }
        }

        $listOperator = in_array($this->operator, [FilterOperator::IN, FilterOperator::NOT_IN], true);

        if ($listOperator && (!is_array($this->value) || $this->value === [])) {
            throw new InvalidInputException(sprintf('%s filters require a non-empty value list.', $this->operator->value));
        }

        if (!$listOperator && is_array($this->value)) {
            throw new InvalidInputException(sprintf('%s filters require a scalar value.', $this->operator->value));
        }
    }

    public function field(): string
    {
        return $this->field;
    }

    /** @return array{field: string, operator: string, value: scalar|list<scalar>} */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator->value,
            'value' => $this->value,
        ];
    }
}
