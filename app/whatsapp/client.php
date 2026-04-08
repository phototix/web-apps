<?php

declare(strict_types=1);

// WhatsApp API Client

function app_whatsapp_api_call(string $method, string $endpoint, array $data = [], ?string $apiKey = null): array {
    $baseUrl = app_whatsapp_api_endpoint();
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init();
    
    $headers = ['Content-Type: application/json'];
    if ($apiKey) {
        $headers[] = 'x-api-key: ' . $apiKey;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    
    if ($method !== 'GET' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('WAHA API connection error: ' . $error);
    }
    
    curl_close($ch);
    
    $result = json_decode($response, true) ?? [];
    
    if ($httpCode >= 400) {
        $errorMessage = $result['message'] ?? $result['error'] ?? 'Unknown error';
        throw new Exception('WAHA API error: ' . $errorMessage, $httpCode);
    }
    
    return $result;
}

function app_whatsapp_api_get(string $endpoint, ?string $apiKey = null): array {
    return app_whatsapp_api_call('GET', $endpoint, [], $apiKey);
}

function app_whatsapp_api_get_binary(string $endpoint, ?string $apiKey = null): string {
    $baseUrl = app_whatsapp_api_endpoint();
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init();
    
    $headers = [];
    if ($apiKey) {
        $headers[] = 'x-api-key: ' . $apiKey;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_BINARYTRANSFER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('WAHA API connection error: ' . $error);
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        // Try to parse error from JSON if possible
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['message'] ?? $errorData['error'] ?? 'Unknown error';
        throw new Exception('WAHA API error: ' . $errorMessage, $httpCode);
    }
    
    return $response;
}

function app_whatsapp_api_post(string $endpoint, array $data = [], ?string $apiKey = null): array {
    return app_whatsapp_api_call('POST', $endpoint, $data, $apiKey);
}

function app_whatsapp_api_put(string $endpoint, array $data = [], ?string $apiKey = null): array {
    return app_whatsapp_api_call('PUT', $endpoint, $data, $apiKey);
}

function app_whatsapp_api_delete(string $endpoint, ?string $apiKey = null): array {
    return app_whatsapp_api_call('DELETE', $endpoint, [], $apiKey);
}

function app_whatsapp_test_connection(): bool {
    try {
        $response = app_whatsapp_api_get('/health');
        return isset($response['status']) && $response['status'] === 'ok';
    } catch (Exception $e) {
        app_log('WAHA connection test failed: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}