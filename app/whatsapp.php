<?php

declare(strict_types=1);

// WhatsApp Integration Main Service File

function app_whatsapp_base_url(): string {
    return app_env('APP_BASE_URL', 'http://localhost');
}

function app_whatsapp_api_endpoint(?int $userId = null): string {
    $defaultEndpoint = app_env('WHATSAPP_API_ENDPOINT', 'http://localhost:3000');
    $user = $userId !== null ? app_find_user_by_id(app_db(), $userId) : app_current_user();
    if (!$user) {
        return $defaultEndpoint;
    }

    $effectiveUser = app_get_effective_user($user);
    $settings = [];
    if (!empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }

    $customEndpoint = trim((string) ($settings['waha_endpoint'] ?? ''));
    $customApiKey = trim((string) ($settings['waha_api_key'] ?? ''));
    if ($customEndpoint !== '' && $customApiKey !== '') {
        return $customEndpoint;
    }

    return $defaultEndpoint;
}

function app_whatsapp_api_key(?int $userId = null): string {
    $defaultApiKey = app_env('WHATSAPP_API_KEY', '');
    $user = $userId !== null ? app_find_user_by_id(app_db(), $userId) : app_current_user();
    if (!$user) {
        return $defaultApiKey;
    }

    $effectiveUser = app_get_effective_user($user);
    $settings = [];
    if (!empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }

    $customEndpoint = trim((string) ($settings['waha_endpoint'] ?? ''));
    $customApiKey = trim((string) ($settings['waha_api_key'] ?? ''));
    if ($customEndpoint !== '' && $customApiKey !== '') {
        return $customApiKey;
    }

    return $defaultApiKey;
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
    $effectiveUser = app_get_effective_user($user);
    $currentSessions = app_whatsapp_count_user_sessions($effectiveUser['id']);
    $tierLimits = app_tier_limits()[$effectiveUser['tier']] ?? app_tier_limits()['basic'];
    
    return $currentSessions < $tierLimits['max_sessions'];
}

function app_can_create_group(array $user): bool {
    $effectiveUser = app_get_effective_user($user);
    if ($effectiveUser['role'] === 'superadmin') return true;
    if ($effectiveUser['role'] === 'admin') return true;
    
    $tierLimits = app_tier_limits()[$effectiveUser['tier']] ?? app_tier_limits()['basic'];
    return in_array('group_management', $tierLimits['features']);
}

function app_get_session_limit(array $user): int {
    $effectiveUser = app_get_effective_user($user);
    $tierLimits = app_tier_limits()[$effectiveUser['tier']] ?? app_tier_limits()['basic'];
    return $tierLimits['max_sessions'];
}

function app_get_user_tier_features(array $user): array {
    $effectiveUser = app_get_effective_user($user);
    $tierLimits = app_tier_limits()[$effectiveUser['tier']] ?? app_tier_limits()['basic'];
    return $tierLimits['features'];
}

// Include WhatsApp service modules
require_once __DIR__ . '/whatsapp/client.php';
require_once __DIR__ . '/whatsapp/sessions.php';
require_once __DIR__ . '/whatsapp/groups.php';
require_once __DIR__ . '/whatsapp/messages.php';
require_once __DIR__ . '/whatsapp/webhooks.php';
require_once __DIR__ . '/whatsapp/categories.php';
