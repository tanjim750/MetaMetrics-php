<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

enum MetricUnit: string
{
    case MONEY = 'money';
    case COUNT = 'count';
    case RATIO = 'ratio';
    case PERCENT = 'percent';
}
