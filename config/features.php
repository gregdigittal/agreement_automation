<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    | Set to true when the feature is ready for production use.
    | Phase 2 features are disabled by default.
    */

    'redlining' => env('FEATURE_REDLINING', true),
    'regulatory_compliance' => env('FEATURE_REGULATORY_COMPLIANCE', true),
    'advanced_analytics' => env('FEATURE_ADVANCED_ANALYTICS', false),
    'vendor_portal' => env('FEATURE_VENDOR_PORTAL', true),
    'meilisearch' => env('FEATURE_MEILISEARCH', false),
];
