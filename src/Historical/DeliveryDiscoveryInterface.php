<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use MetaMetrics\Insights\InsightsDTO;
use MetaMetrics\Insights\Metric\Metric;

interface DeliveryDiscoveryInterface
{
    /** @return list<Metric> */
    public function requiredMetrics(): array;

    public function assess(InsightsDTO $insights): DeliveryAssessment;
}
