<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Parser;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Insights\Metric\PurchaseActionMapping;

final readonly class ActionValueParser
{
    private PurchaseActionMapping $purchaseActionMapping;

    /** @param list<string>|PurchaseActionMapping $purchaseActionTypes */
    public function __construct(array|PurchaseActionMapping $purchaseActionTypes = ActionParser::PURCHASE_ACTION_TYPES)
    {
        $this->purchaseActionMapping = $purchaseActionTypes instanceof PurchaseActionMapping
            ? $purchaseActionTypes
            : new PurchaseActionMapping($purchaseActionTypes);
    }

    public function purchaseValue(mixed $actionValues): ?float
    {
        return $this->purchaseValueForType(
            $actionValues,
            $this->preferredPurchaseActionType($actionValues),
        );
    }

    public function purchaseValueForType(mixed $actionValues, ?string $actionType): ?float
    {
        if ($actionValues === null) {
            return null;
        }

        if ($actionType === null) {
            return $this->containsPurchase($actionValues) ? null : 0.0;
        }

        if (!$this->purchaseActionMapping->supports($actionType)) {
            throw new InvalidInputException('Purchase action type is not included in the configured mapping.');
        }

        return $this->values($actionValues)[$actionType] ?? 0.0;
    }

    public function containsPurchase(mixed $actionValues): bool
    {
        return $this->preferredPurchaseActionType($actionValues) !== null;
    }

    private function preferredPurchaseActionType(mixed $actionValues): ?string
    {
        $values = $this->values($actionValues);

        foreach ($this->purchaseActionMapping->actionTypes() as $actionType) {
            if (array_key_exists($actionType, $values)) {
                return $actionType;
            }
        }

        return null;
    }

    /** @return array<string, float> */
    private function values(mixed $actionValues): array
    {
        if ($actionValues === null) {
            return [];
        }

        if (!is_array($actionValues) || !array_is_list($actionValues)) {
            throw new UnexpectedResponseException('Meta Insights "action_values" must be a list.');
        }

        $values = [];

        foreach ($actionValues as $action) {
            if (!is_array($action)
                || !isset($action['action_type'])
                || !is_string($action['action_type'])
                || $action['action_type'] === ''
                || !array_key_exists('value', $action)) {
                throw new UnexpectedResponseException('Meta Insights "action_values" contains a malformed action.');
            }

            if (array_key_exists($action['action_type'], $values)) {
                throw new UnexpectedResponseException(
                    'Meta Insights "action_values" contains a duplicate action type.',
                );
            }

            $values[$action['action_type']] = $this->number($action['value']);
        }

        return $values;
    }

    private function number(mixed $value): float
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
            throw new UnexpectedResponseException('Meta Insights "action_values" action value must be numeric.');
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            throw new UnexpectedResponseException('Meta Insights "action_values" action value must be finite.');
        }

        return $number;
    }
}
