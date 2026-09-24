<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

use MetaMetrics\Exception\InvalidInputException;

final readonly class PurchaseActionMapping
{
    public const DEFAULT_ACTION_TYPES = [
        'omni_purchase',
        'purchase',
        'offsite_conversion.fb_pixel_purchase',
        'app_custom_event.fb_mobile_purchase',
        'onsite_conversion.purchase',
        'offline_conversion.purchase',
    ];

    /** @var non-empty-list<string> */
    private array $actionTypes;

    /** @param non-empty-list<string> $actionTypes */
    public function __construct(array $actionTypes = self::DEFAULT_ACTION_TYPES)
    {
        if ($actionTypes === [] || !array_is_list($actionTypes)) {
            throw new InvalidInputException('Purchase action mapping must be a non-empty list.');
        }

        foreach ($actionTypes as $actionType) {
            if (!is_string($actionType) || trim($actionType) === '') {
                throw new InvalidInputException('Purchase action types must be non-empty strings.');
            }
        }

        if (count($actionTypes) !== count(array_unique($actionTypes))) {
            throw new InvalidInputException('Purchase action mapping must not contain duplicates.');
        }

        $this->actionTypes = array_values($actionTypes);
    }

    /** @return non-empty-list<string> */
    public function actionTypes(): array
    {
        return $this->actionTypes;
    }

    public function supports(string $actionType): bool
    {
        return in_array($actionType, $this->actionTypes, true);
    }

    /** @param array<string, mixed> ...$sets */
    public function preferredCommonType(array ...$sets): ?string
    {
        foreach ($this->actionTypes as $actionType) {
            foreach ($sets as $set) {
                if (!array_key_exists($actionType, $set)) {
                    continue 2;
                }
            }

            return $actionType;
        }

        return null;
    }
}
