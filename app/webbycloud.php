<?php

declare(strict_types=1);

function app_webbycloud_config(): array
{
    return [
        'client_id' => (string) app_env('WEBBYCLOUD_CLIENT_ID', ''),
        'client_secret' => (string) app_env('WEBBYCLOUD_CLIENT_SECRET', ''),
        'auth_url' => (string) app_env('WEBBYCLOUD_AUTH_URL', ''),
        'token_url' => (string) app_env('WEBBYCLOUD_TOKEN_URL', ''),
        'user_info_url' => (string) app_env('WEBBYCLOUD_USER_INFO_URL', ''),
        'redirect_uri' => (string) app_env('WEBBYCLOUD_REDIRECT_URI', ''),
        'scopes' => (string) app_env('WEBBYCLOUD_SCOPES', ''),
    ];
}

function app_webbycloud_validate_config(array $config): bool
{
    return $config['client_id'] !== ''
        && $config['client_secret'] !== ''
        && $config['auth_url'] !== ''
        && $config['token_url'] !== ''
        && $config['user_info_url'] !== ''
        && $config['redirect_uri'] !== ''
        && $config['scopes'] !== '';
}

function app_webbycloud_authorize_url(array $config, string $state): string
{
    $query = http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => $config['scopes'],
        'state' => $state,
    ], '', '&', PHP_QUERY_RFC3986);

    return rtrim($config['auth_url'], '?') . '?' . $query;
}

function app_webbycloud_exchange_code(array $config, string $code): array
{
    $payload = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $config['redirect_uri'],
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
    ], '', '&', PHP_QUERY_RFC3986);

    $ch = curl_init();
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize OAuth request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $config['token_url'],
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        $message = $response !== false ? $response : $curlError;
        throw new RuntimeException('Token exchange failed: ' . $message);
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded) || empty($decoded['access_token'])) {
        throw new RuntimeException('Invalid token response from WebbyCloud.');
    }

    return $decoded;
}

function app_webbycloud_fetch_user(array $config, string $accessToken): array
{
    $ch = curl_init();
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize profile request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $config['user_info_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'OCS-APIRequest: true',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        $message = $response !== false ? $response : $curlError;
        throw new RuntimeException('Profile request failed: ' . $message);
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid profile response from WebbyCloud.');
    }

    $data = $decoded['ocs']['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    return [
        'id' => (string) ($data['id'] ?? ''),
        'email' => (string) ($data['email'] ?? ''),
        'display_name' => (string) ($data['displayname'] ?? ''),
    ];
}

function app_webbycloud_list_files(array $config, string $accessToken, string $cloudUserId = '', string $folderPath = ''): array
{
    $baseUrl = rtrim(preg_replace('#/ocs/v2.php/.*#', '', $config['user_info_url']), '/');
    $username = $cloudUserId !== '' ? $cloudUserId : 'user';
    $folderPath = ltrim($folderPath, '/');
    $folderSegment = $folderPath !== '' ? '/' . $folderPath : '';
    $filesUrl = $baseUrl . '/remote.php/dav/files/' . $username . $folderSegment;

    $ch = curl_init();
    if ($ch === false) {
        throw new RuntimeException('Unable to initialize files request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $filesUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/xml',
            'Content-Type: application/xml',
            'Depth: 1',
        ],
        CURLOPT_POSTFIELDS => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:displayname/><d:getcontentlength/><d:getlastmodified/><d:resourcetype/></d:prop></d:propfind>',
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        $message = $response !== false ? $response : $curlError;
        throw new RuntimeException('Files request failed: ' . $message);
    }

    $files = [];
    $folders = [];
    $hrefBase = '/remote.php/dav/files/' . $username . $folderSegment;

    if (preg_match_all('/<d:response>(.*?)<\/d:response>/s', $response, $matches)) {
        foreach ($matches[1] as $responseBlock) {
            $href = '';
            $name = '';
            $size = 0;
            $modified = '';
            $isCollection = preg_match('/<d:collection\s*\/?>/i', $responseBlock) || preg_match('/<d:collection>\s*<\/d:collection>/i', $responseBlock);

            if (preg_match('/<d:href>([^<]+)<\/d:href>/', $responseBlock, $m)) {
                $href = htmlspecialchars_decode($m[1]);
            }
            if (preg_match('/<d:displayname>([^<]+)<\/d:displayname>/', $responseBlock, $m)) {
                $name = htmlspecialchars_decode($m[1]);
            }
            if (preg_match('/<d:getcontentlength>([^<]*)<\/d:getcontentlength>/', $responseBlock, $m)) {
                $size = (int) $m[1];
            }
            if (preg_match('/<d:getlastmodified>([^<]+)<\/d:getlastmodified>/', $responseBlock, $m)) {
                $modified = htmlspecialchars_decode($m[1]);
            }

            if ($href === '') {
                continue;
            }

            $hrefPath = (string) (parse_url($href, PHP_URL_PATH) ?? $href);
            if (strpos($hrefPath, $hrefBase) !== 0) {
                continue;
            }

            $relativePath = ltrim(substr($hrefPath, strlen($hrefBase)), '/');
            $relativePath = rtrim($relativePath, '/');
            if ($relativePath === '') {
                continue;
            }

            $pathParts = explode('/', $relativePath);
            if (count($pathParts) !== 1) {
                continue;
            }

            if ($isCollection) {
                $folders[] = [
                    'name' => $name !== '' ? $name : $pathParts[0],
                    'path' => $relativePath,
                    'type' => 'folder',
                    'size' => 0,
                    'modified' => $modified,
                ];
            } else {
                $files[] = [
                    'name' => $name !== '' ? $name : $pathParts[0],
                    'path' => $relativePath,
                    'type' => 'file',
                    'size' => $size,
                    'modified' => $modified,
                ];
            }
        }
    }

    usort($folders, function($a, $b) { return strcmp($a['name'], $b['name']); });
    usort($files, function($a, $b) { return strcmp($a['name'], $b['name']); });

    return array_merge($folders, $files);
}
