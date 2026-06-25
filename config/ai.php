<?php

return [
    'enabled' => env('AI_ENRICHMENT_ENABLED', true),

    'version' => env('AI_ENRICHMENT_VERSION', '1'),

    'batch_limit' => (int) env('AI_ENRICHMENT_BATCH_LIMIT', 50),

    'schedule_enabled' => env('AI_ENRICHMENT_SCHEDULE_ENABLED', false),

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        'model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 120),
        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 4096),
    ],

    'vision_models' => [
        'google/gemini-2.5-flash' => 'Gemini 2.5 Flash (recommended)',
        'google/gemini-2.5-flash-image' => 'Gemini 2.5 Flash Image',
        'openai/gpt-4o' => 'GPT-4o',
        'openai/gpt-4o-mini' => 'GPT-4o Mini',
        'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet',
    ],

    'enrichment' => [
        'category_fields' => [
            'name_ur',
            'short_description_en',
            'short_description_ur',
            'description_en',
            'description_ur',
            'seo_title',
            'seo_description',
            'seo_keywords',
            'seo_focus_keyword',
            'hs_code',
            'usage_en',
            'usage_ur',
            'benefits_en',
            'benefits_ur',
            'warnings_en',
            'warnings_ur',
        ],

        'product_fields' => [
            'name_ur',
            'short_description_en',
            'short_description_ur',
            'description_en',
            'description_ur',
            'seo_title',
            'seo_description',
            'seo_keywords',
            'seo_focus_keyword',
            'hs_code',
            'usage_en',
            'usage_ur',
        ],
    ],
];
