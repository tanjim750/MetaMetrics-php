<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

enum MetricAggregation: string
{
    case SUM = 'sum';
    case META_ONLY = 'meta_only';
    case RECALCULATE = 'recalculate';
}
