<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

enum DeliveryStatus: string
{
    case DELIVERED = 'delivered';
    case NOT_DELIVERED = 'not_delivered';
    case INSUFFICIENT_EVIDENCE = 'insufficient_evidence';
}
