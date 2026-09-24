<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Parser;

use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\InsightsRawData;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricRegistry;

final readonly class InsightsParser
{
    public function __construct(
        private ActionParser $actionParser = new ActionParser(),
        private ActionValueParser $actionValueParser = new ActionValueParser(),
        private MetricRegistry $registry = new MetricRegistry(),
    ) {
    }

    /**
     * @param array<string, mixed> $row
     * @param list<Metric> $foundationalMetrics
     * @return array{
     *     entityId: string,
     *     entityName: string|null,
     *     campaignId: string|null,
     *     adSetId: string|null,
     *     adId: string|null,
     *     dateRange: DateRange,
     *     foundational: array<string, float|null>,
     *     rawData: InsightsRawData
     * }
     */
    public function parse(
        array $row,
        InsightsLevel $level,
        array $foundationalMetrics,
        ?array $adSetConfiguration = null,
    ): array
    {
        [$idField, $nameField] = match ($level) {
            InsightsLevel::ACCOUNT => ['account_id', 'account_name'],
            InsightsLevel::CAMPAIGN => ['campaign_id', 'campaign_name'],
            InsightsLevel::AD_SET => ['adset_id', 'adset_name'],
            InsightsLevel::AD => ['ad_id', 'ad_name'],
        };

        return [
            'entityId' => $this->requiredString($row, $idField),
            'entityName' => $this->optionalString($row, $nameField),
            'campaignId' => $level === InsightsLevel::ACCOUNT
                ? null
                : $this->requiredString($row, 'campaign_id'),
            'adSetId' => in_array($level, [InsightsLevel::AD_SET, InsightsLevel::AD], true)
                ? $this->requiredString($row, 'adset_id')
                : null,
            'adId' => $level === InsightsLevel::AD ? $this->requiredString($row, 'ad_id') : null,
            'dateRange' => new DateRange(
                $this->requiredString($row, 'date_start'),
                $this->requiredString($row, 'date_stop'),
            ),
            'foundational' => $this->foundational($row, $foundationalMetrics),
            'rawData' => new InsightsRawData(
                spend: $this->rawNumber($row, 'spend'),
                actions: $this->rawList($row, 'actions'),
                costPerActionType: $this->rawList($row, 'cost_per_action_type'),
                optimizationGoal: $this->configurationString($adSetConfiguration, 'optimization_goal'),
                promotedObject: $this->promotedObject($adSetConfiguration),
            ),
        ];
    }

    /** @param array<string, mixed> $row @param list<Metric> $metrics @return array<string, float|null> */
    private function foundational(array $row, array $metrics): array
    {
        $values = [];
        $needsPurchases = in_array(Metric::PURCHASES, $metrics, true);
        $needsPurchaseValue = in_array(Metric::PURCHASE_VALUE, $metrics, true);
        $canAlignPurchases = $needsPurchases
            && $needsPurchaseValue
            && ($row['actions'] ?? null) !== null
            && ($row['action_values'] ?? null) !== null;
        $sharedActionType = null;

        if ($canAlignPurchases) {
            $sharedActionType = $this->actionParser->commonPurchaseActionType(
                $row['actions'],
                $row['action_values'],
            );
        }

        foreach ($metrics as $metric) {
            $values[$metric->value] = match ($metric) {
                Metric::PURCHASES => $canAlignPurchases
                    ? $this->actionParser->purchasesForType($row['actions'] ?? null, $sharedActionType)
                    : $this->actionParser->purchases($row['actions'] ?? null),
                Metric::PURCHASE_VALUE => $canAlignPurchases
                    ? $this->actionValueParser->purchaseValueForType($row['action_values'] ?? null, $sharedActionType)
                    : $this->actionValueParser->purchaseValue($row['action_values'] ?? null),
                default => $this->optionalNumber($row, $this->registry->sourceField($metric)),
            };
        }

        return $values;
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value) || $value === '') {
            throw new UnexpectedResponseException(sprintf('Meta Insights row requires a valid "%s" field.', $field));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function optionalString(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!is_string($data[$field]) || $data[$field] === '') {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" field must be a string.', $field));
        }

        return $data[$field];
    }

    /** @param array<string, mixed> $data */
    private function optionalNumber(array $data, ?string $field): ?float
    {
        if ($field === null || !array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        $value = $data[$field];

        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" field must be numeric.', $field));
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" field must be finite.', $field));
        }

        return $number;
    }

    /** @param array<string, mixed> $data */
    private function rawNumber(array $data, string $field): string|int|float|null
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        $value = $data[$field];

        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" field must be numeric.', $field));
        }

        return $value;
    }

    /** @param array<string, mixed> $data @return list<array<string, mixed>>|null */
    private function rawList(array $data, string $field): ?array
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        $value = $data[$field];

        if (!is_array($value) || !array_is_list($value)) {
            throw new UnexpectedResponseException(sprintf('Meta Insights "%s" field must be a list.', $field));
        }

        foreach ($value as $item) {
            if (!is_array($item)) {
                throw new UnexpectedResponseException(sprintf('Meta Insights "%s" contains a malformed item.', $field));
            }
        }

        return $value;
    }

    /** @param array<string, mixed>|null $configuration */
    private function configurationString(?array $configuration, string $field): ?string
    {
        if ($configuration === null || !array_key_exists($field, $configuration) || $configuration[$field] === null) {
            return null;
        }

        if (!is_string($configuration[$field]) || $configuration[$field] === '') {
            throw new UnexpectedResponseException(sprintf('Meta Ad Set configuration "%s" must be a string.', $field));
        }

        return $configuration[$field];
    }

    /** @param array<string, mixed>|null $configuration @return array<string, mixed>|null */
    private function promotedObject(?array $configuration): ?array
    {
        if ($configuration === null
            || !array_key_exists('promoted_object', $configuration)
            || $configuration['promoted_object'] === null) {
            return null;
        }

        if (!is_array($configuration['promoted_object'])) {
            throw new UnexpectedResponseException('Meta Ad Set configuration "promoted_object" must be an object.');
        }

        return $configuration['promoted_object'];
    }
}
