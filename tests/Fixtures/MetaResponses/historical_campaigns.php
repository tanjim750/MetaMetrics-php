<?php

declare(strict_types=1);

return [
    [
        'campaign_id' => 'campaign-before-range',
        'campaign_name' => 'Paused after delivery',
        'date_start' => '2026-09-01',
        'date_stop' => '2026-09-24',
        'spend' => '500.00',
        'impressions' => '10000',
        'reach' => '8000',
        'actions' => [],
        'action_values' => [['action_type' => 'purchase', 'value' => '1000']],
    ],
    [
        'campaign_id' => 'campaign-created-in-range',
        'campaign_name' => 'Created during range',
        'date_start' => '2026-09-01',
        'date_stop' => '2026-09-24',
        'spend' => '0',
        'impressions' => '2500',
        'reach' => '1900',
        'actions' => [],
        'action_values' => [],
    ],
    [
        'campaign_id' => 'campaign-without-delivery',
        'campaign_name' => 'Active without delivery',
        'date_start' => '2026-09-01',
        'date_stop' => '2026-09-24',
        'spend' => '0',
        'impressions' => '0',
        'reach' => '0',
        'actions' => [['action_type' => 'purchase', 'value' => '3']],
        'action_values' => [['action_type' => 'purchase', 'value' => '60']],
    ],
];
