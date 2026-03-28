<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Store
    |--------------------------------------------------------------------------
    | The default store profile used by the agent. Maps to creem.profiles.{name}.
    */
    'default_store' => env('CREEM_AGENT_DEFAULT_STORE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Stores
    |--------------------------------------------------------------------------
    | Multiple stores can be monitored. Each maps to a creem profile.
    */
    'stores' => [
        'default' => [
            'profile' => 'default',
            'heartbeat_frequency' => 4, // hours
            'notifications' => ['database'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | State Storage
    |--------------------------------------------------------------------------
    | Where heartbeat state files are stored.
    */
    'state_path' => storage_path('creem-agent'),

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'slack_webhook_url' => env('CREEM_AGENT_SLACK_WEBHOOK'),
        'telegram_bot_token' => env('CREEM_AGENT_TELEGRAM_TOKEN'),
        'telegram_chat_id' => env('CREEM_AGENT_TELEGRAM_CHAT_ID'),
        'telegram_api_base' => env('CREEM_AGENT_TELEGRAM_API_BASE', 'https://api.telegram.org'),
        'discord_webhook_url' => env('CREEM_AGENT_DISCORD_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Ownership
    |--------------------------------------------------------------------------
    | `laravel` means the package owns Telegram directly through
    | `/creem-agent/telegram/webhook` and direct Bot API sends.
    | `openclaw` means Telegram is owned by OpenClaw gateway, following
    | https://docs.openclaw.ai/channels/telegram.
    */
    'telegram' => [
        'mode' => env('CREEM_AGENT_TELEGRAM_MODE', env('CREEM_AGENT_OPENCLAW', false) ? 'openclaw' : 'laravel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quiet Hours
    |--------------------------------------------------------------------------
    | Don't send non-critical notifications during these hours.
    */
    'quiet_hours' => [
        'start' => 23, // 11 PM
        'end' => 7,    // 7 AM
        'critical_threshold' => 1000, // Alert anyway if amount > $10 (cents)
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenClaw (optional)
    |--------------------------------------------------------------------------
    */
    'openclaw' => [
        'enabled' => env('CREEM_AGENT_OPENCLAW', false),
        'endpoint' => env('OPENCLAW_ENDPOINT'),
        'telegram' => [
            'bot_token' => env('OPENCLAW_TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN', env('CREEM_AGENT_TELEGRAM_TOKEN'))),
            'dm_policy' => env('OPENCLAW_TELEGRAM_DM_POLICY', 'pairing'),
            'allow_from' => env('OPENCLAW_TELEGRAM_ALLOW_FROM'),
            'group_policy' => env('OPENCLAW_TELEGRAM_GROUP_POLICY', 'allowlist'),
            'group_allow_from' => env('OPENCLAW_TELEGRAM_GROUP_ALLOW_FROM'),
            'require_mention' => env('OPENCLAW_TELEGRAM_REQUIRE_MENTION', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Natural Language / LLM Routing
    |--------------------------------------------------------------------------
    | The agent uses Laravel AI SDK to classify free-form user requests into
    | operational intents. If LLM routing fails or is unavailable, the package
    | falls back to the built-in regex parser.
    */
    'llm' => [
        'enabled' => env('CREEM_AGENT_LLM_ENABLED', true),
        'provider' => env('CREEM_AGENT_LLM_PROVIDER', 'openai'),
        'model' => env('CREEM_AGENT_LLM_MODEL'),
        'timeout' => (int) env('CREEM_AGENT_LLM_TIMEOUT', 30),
        'fallback_to_rules' => env('CREEM_AGENT_LLM_FALLBACK_TO_RULES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Daemon
    |--------------------------------------------------------------------------
    */
    'daemon' => [
        'queue' => 'creem-agent',
        'max_time' => 3600, // seconds
    ],
];
