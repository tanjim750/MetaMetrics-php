<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use MetaMetrics\Insights\InsightsLevel;

enum HistoricalLevel: string
{
    case CAMPAIGN = 'campaign';
    case AD_SET = 'adset';
    case AD = 'ad';

    public function toInsightsLevel(): InsightsLevel
    {
        return InsightsLevel::from($this->value);
    }
}
