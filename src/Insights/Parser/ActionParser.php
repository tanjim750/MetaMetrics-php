<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Parser;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Insights\Metric\PurchaseActionMapping;

final readonly class ActionParser
{
    public const PURCHASE_ACTION_TYPES = PurchaseActionMapping::DEFAULT_ACTION_TYPES;

    private PurchaseActionMapping $purchaseActionMapping;

    /** @param list<string>|PurchaseActionMapping $purchaseActionTypes */
    public function __construct(array|PurchaseActionMapping $purchaseActionTypes = self::PURCHASE_ACTION_TYPES)
    {
        $this->purchaseActionMapping = $purchaseActionTypes instanceof PurchaseActionMapping
            ? $purchaseActionTypes
            : new PurchaseActionMapping($purchaseActionTypes);
    }

    public function purchases(mixed $actions): ?float
    {
        return $this->extract($actions, 'actions');
    }

    public function purchasesForType(mixed $actions, ?string $actionType): ?float
    {
        if ($actions === null) {
            return null;
        }

        if ($actionType === null) {
            return $this->containsPurchase($actions) ? null : 0.0;
        }

        if (!$this->purchaseActionMapping->supports($actionType)) {
            throw new InvalidInputException('Purchase action type is not included in the configured mapping.');
        }

        return $this->values($actions, 'actions')[$actionType] ?? 0.0;
    }

    public function commonPurchaseActionType(mixed $actions, mixed $actionValues): ?string
    {
        $actionTypes = array_keys($this->values($actions, 'actions'));
        $valueTypes = array_keys($this->values($actionValues, 'action_values'));

        return $this->purchaseActionMapping->preferredCommonType(
            array_fill_keys($actionTypes, true),
            array_fill_keys($valueTypes, true),
        );
    }

    public function containsPurchase(mixed $actions): bool
    {
        foreach ($this->purchaseActionMapping->actionTypes() as $actionType) {
            if (array_key_exists($actionType, $this->values($actions, 'actions'))) {
                return true;
            }
        }

        return false;
    }

    private function extract(mixed $actions, string $field): ?float
    {
        if ($actions === null) {
            return null;
        }

        $values = $this->values($actions, $field);

        foreach ($this->purchaseActionMapping->actionTypes() as $actionType) {
            if (array_key_exists($actionType, $values)) {
                return $values[$actionType];
            }
        }

        return 0.0;
    }

    /** @return array<string, float> */
    private function values(mixed $actions, string $field): array
    {
        if (!is_array($actions) || !array_is_list($actions)) {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" must be a list.', $field));
        }

        $values = [];

        foreach ($actions as $action) {
            if (!is_array($action)
                || !isset($action['action_type'])
                || !is_string($action['action_type'])
                || $action['action_type'] === ''
                || !array_key_exists('value', $action)) {
                throw new UnexpectedResponseException(sprintf('Meta Insights "%s" contains a malformed action.', $field));
            }

            if (array_key_exists($action['action_type'], $values)) {
                throw new UnexpectedResponseException(sprintf(
                    'Meta Insights "%s" contains a duplicate action type.',
                    $field,
                ));
            }

            $values[$action['action_type']] = $this->number($action['value'], $field);
        }

        return $values;
    }

    private function number(mixed $value, string $field): float
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" action value must be numeric.', $field));
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" action value must be finite.', $field));
        }

        return $number;
    }
}
