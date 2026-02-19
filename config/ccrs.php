<?php

return [
    'ai_worker_url' => env('AI_WORKER_URL', 'http://ai-worker:8001'),
    'ai_worker_secret' => env('AI_WORKER_SECRET', ''),
    'ai_analysis_timeout' => env('AI_ANALYSIS_TIMEOUT', 120),
    'boldsign_api_key' => env('BOLDSIGN_API_KEY', ''),
    'boldsign_api_url' => env('BOLDSIGN_API_URL', 'https://api.boldsign.com'),
    'boldsign_webhook_secret' => env('BOLDSIGN_WEBHOOK_SECRET', ''),
    'tito_api_key' => env('TITO_API_KEY', ''),
    'teams_team_id' => env('TEAMS_TEAM_ID', ''),
    'teams_channel_id' => env('TEAMS_CHANNEL_ID', ''),
    'azure_ad' => [
        'tenant_id' => env('AZURE_AD_TENANT_ID', ''),
        'group_map' => array_filter([
            env('AZURE_AD_GROUP_SYSTEM_ADMIN', '') => 'system_admin',
            env('AZURE_AD_GROUP_LEGAL', '') => 'legal',
            env('AZURE_AD_GROUP_COMMERCIAL', '') => 'commercial',
            env('AZURE_AD_GROUP_FINANCE', '') => 'finance',
            env('AZURE_AD_GROUP_OPERATIONS', '') => 'operations',
            env('AZURE_AD_GROUP_AUDIT', '') => 'audit',
        ], fn ($v, $k) => $k !== '', ARRAY_FILTER_USE_BOTH),
    ],
    'contracts_disk' => 's3',
    'wiki_contracts_disk' => 's3',
    'otel_enabled' => env('OTEL_ENABLED', false),
];
