<?php

declare(strict_types=1);

namespace MetaMetrics\Query;

use MetaMetrics\Exception\InvalidInputException;

final readonly class Fields
{
    /** @var list<string> */
    private array $values;

    /** @param list<string> $values */
    public function __construct(array $values)
    {
        if ($values === []) {
            throw new InvalidInputException('At least one field must be selected.');
        }

        foreach ($values as $field) {
            if (!is_string($field) || preg_match('/\A[a-z][a-z0-9_]*\z/D', $field) !== 1) {
                throw new InvalidInputException('Selected fields must use valid Meta field names.');
            }
        }

        $this->values = array_values(array_unique($values));
    }

    /** @return list<string> */
    public function values(): array
    {
        return $this->values;
    }

    /** @param list<string> $fields */
    public function withRequired(array $fields): self
    {
        return new self(array_values(array_unique([...$fields, ...$this->values])));
    }

    public function toQueryValue(): string
    {
        return implode(',', $this->values);
    }
}
