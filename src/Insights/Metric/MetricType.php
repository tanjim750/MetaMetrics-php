<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

enum MetricType: string
{
    case DIRECT = 'direct';
    case ACTION = 'action';
    case ACTION_VALUE = 'action_value';
    case DERIVED = 'derived';
}
