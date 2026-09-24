<?php

declare(strict_types=1);

namespace MetaMetrics\Insights;

enum InsightsFilterField: string
{
    case CAMPAIGN_ID = 'campaign.id';
    case CAMPAIGN_NAME = 'campaign.name';
    case CAMPAIGN_EFFECTIVE_STATUS = 'campaign.effective_status';
    case AD_SET_ID = 'adset.id';
    case AD_SET_NAME = 'adset.name';
    case AD_SET_EFFECTIVE_STATUS = 'adset.effective_status';
    case AD_ID = 'ad.id';
    case AD_NAME = 'ad.name';
    case AD_EFFECTIVE_STATUS = 'ad.effective_status';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $field): string => $field->value, self::cases());
    }
}
