<?php

declare(strict_types=1);

namespace MetaMetrics\Insights;

enum InsightsLevel: string
{
    case ACCOUNT = 'account';
    case CAMPAIGN = 'campaign';
    case AD_SET = 'adset';
    case AD = 'ad';
}
