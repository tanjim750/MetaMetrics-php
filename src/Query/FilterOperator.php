<?php

declare(strict_types=1);

namespace MetaMetrics\Query;

enum FilterOperator: string
{
    case EQUAL = 'EQUAL';
    case NOT_EQUAL = 'NOT_EQUAL';
    case IN = 'IN';
    case NOT_IN = 'NOT_IN';
    case CONTAIN = 'CONTAIN';
    case NOT_CONTAIN = 'NOT_CONTAIN';
}
