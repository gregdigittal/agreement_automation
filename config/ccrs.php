<?php

return [
    'ai_worker_url' => env('AI_WORKER_URL', 'http://ai-worker:8001'),
    'ai_worker_secret' => env('AI_WORKER_SECRET', ''),
    'ai_analysis_timeout' => env('AI_ANALYSIS_TIMEOUT', 120),
    'boldsign_api_key' => env('BOLDSIGN_API_KEY', ''),
    'boldsign_api_url' => env('BOLDSIGN_API_URL', 'https://api.boldsign.com'),
    'boldsign_webhook_secret' => env('BOLDSIGN_WEBHOOK_SECRET', ''),
    'azure_ad' => [
        'group_map' => [
            env('AZURE_AD_GROUP_SYSTEM_ADMIN') => 'system_admin',
            env('AZURE_AD_GROUP_LEGAL') => 'legal',
            env('AZURE_AD_GROUP_COMMERCIAL') => 'commercial',
            env('AZURE_AD_GROUP_FINANCE') => 'finance',
            env('AZURE_AD_GROUP_OPERATIONS') => 'operations',
            env('AZURE_AD_GROUP_AUDIT') => 'audit',
        ],
    ],
    'contracts_disk' => 's3',
    'tito_api_key' => env('TITO_API_KEY', ''),
    'merchant_agreement_template_s3_key' => env('MA_TEMPLATE_S3_KEY', 'templates/merchant_agreement_master.docx'),
    'wiki_contracts_disk' => 's3',
    'teams' => [
        'team_id' => env('TEAMS_TEAM_ID', ''),
        'channel_id' => env('TEAMS_CHANNEL_ID', ''),
        'graph_scope' => 'https://graph.microsoft.com/.default',
        'graph_base_url' => 'https://graph.microsoft.com/v1.0',
        'token_endpoint' => 'https://login.microsoftonline.com/' . (env('AZURE_AD_TENANT_ID') ?? '') . '/oauth2/v2.0/token',
    ],

];
