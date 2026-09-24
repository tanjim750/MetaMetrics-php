<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

enum Metric: string
{
    case SPEND = 'spend';
    case PURCHASES = 'purchases';
    case COST_PER_PURCHASE = 'cost_per_purchase';
    case PURCHASE_VALUE = 'purchase_value';
    case ROAS = 'roas';
    case IMPRESSIONS = 'impressions';
    case REACH = 'reach';
    case CLICKS = 'clicks';
    case LINK_CLICKS = 'link_clicks';
    case CPC = 'cpc';
    case CTR = 'ctr';
    case CPM = 'cpm';
}
