<?php

declare(strict_types=1);

// WhatsApp Integration Main Service File

function app_whatsapp_base_url(): string {
    return app_env('APP_BASE_URL', 'http://localhost');
}

function app_whatsapp_api_endpoint(): string {
    return app_env('WHATSAPP_API_ENDPOINT', 'http://localhost:3000');
}

function app_whatsapp_api_key(): string {
    return app_env('WHATSAPP_API_KEY', '');
}

function app_tier_limits(): array {
    return [
        'basic' => [
            'max_sessions' => 1,
            'max_groups' => 10,
            'message_history_days' => 7,
            'features' => ['basic_messaging', 'group_view']
        ],
        'business' => [
            'max_sessions' => 3,
            'max_groups' => 50,
            'message_history_days' => 30,
            'features' => ['basic_messaging', 'group_view', 'media_sharing', 'group_management']
        ],
        'enterprise' => [
            'max_sessions' => 5,
            'max_groups' => 200,
            'message_history_days' => 90,
            'features' => ['all_features', 'api_access', 'custom_webhooks', 'analytics']
        ]
    ];
}

function app_can_create_session(array $user): bool {
    $currentSessions = app_whatsapp_count_user_sessions($user['id']);
    $tierLimits = app_tier_limits()[$user['tier']] ?? app_tier_limits()['basic'];
    
    return $currentSessions < $tierLimits['max_sessions'];
}

function app_can_create_group(array $user): bool {
    if ($user['role'] === 'superadmin') return true;
    if ($user['role'] === 'admin') return true;
    
    $tierLimits = app_tier_limits()[$user['tier']] ?? app_tier_limits()['basic'];
    return in_array('group_management', $tierLimits['features']);
}

function app_get_session_limit(array $user): int {
    $tierLimits = app_tier_limits()[$user['tier']] ?? app_tier_limits()['basic'];
    return $tierLimits['max_sessions'];
}

function app_get_user_tier_features(array $user): array {
    $tierLimits = app_tier_limits()[$user['tier']] ?? app_tier_limits()['basic'];
    return $tierLimits['features'];
}

// Include WhatsApp service modules
require_once __DIR__ . '/whatsapp/client.php';
require_once __DIR__ . '/whatsapp/sessions.php';
require_once __DIR__ . '/whatsapp/groups.php';
require_once __DIR__ . '/whatsapp/messages.php';
require_once __DIR__ . '/whatsapp/webhooks.php';