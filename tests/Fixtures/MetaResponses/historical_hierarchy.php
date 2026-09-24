<?php

declare(strict_types=1);

return [
    'campaigns' => [[
        'campaign_id' => 'campaign-1',
        'campaign_name' => 'Delivered Campaign',
        'date_start' => '2026-09-01',
        'date_stop' => '2026-09-24',
        'spend' => '10',
        'impressions' => '100',
        'reach' => '80',
    ]],
    'adsets' => [
        [
            'campaign_id' => 'campaign-1',
            'adset_id' => 'adset-1',
            'adset_name' => 'Delivered Ad Set',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '8',
            'impressions' => '80',
            'reach' => '60',
        ],
        [
            'campaign_id' => 'campaign-without-delivery',
            'adset_id' => 'adset-without-delivered-campaign',
            'adset_name' => 'Independently Delivered Ad Set',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '1',
            'impressions' => '10',
            'reach' => '8',
        ],
    ],
    'ads' => [
        [
            'campaign_id' => 'campaign-1',
            'adset_id' => 'adset-1',
            'ad_id' => 'ad-1',
            'ad_name' => 'Delivered Ad',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '6',
            'impressions' => '60',
            'reach' => '45',
        ],
        [
            'campaign_id' => 'campaign-1',
            'adset_id' => 'adset-without-delivery',
            'ad_id' => 'ad-without-delivered-adset',
            'ad_name' => 'Independently Delivered Ad',
            'date_start' => '2026-09-01',
            'date_stop' => '2026-09-24',
            'spend' => '1',
            'impressions' => '10',
            'reach' => '7',
        ],
    ],
];
