<?php

declare(strict_types=1);

function app_router_mime_type(string $filePath): string
{
    $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));

    return match ($extension) {
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
        'txt' => 'text/plain; charset=UTF-8',
        default => mime_content_type($filePath) ?: 'application/octet-stream',
    };
}

$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicRoot = realpath(__DIR__ . '/public');

if ($publicRoot !== false && $requestPath !== '/') {
    $candidatePath = realpath($publicRoot . $requestPath);

    if ($candidatePath !== false && str_starts_with($candidatePath, $publicRoot . DIRECTORY_SEPARATOR) && is_file($candidatePath)) {
        $extension = strtolower((string) pathinfo($candidatePath, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            require $candidatePath;

            return true;
        }

        $mimeType = app_router_mime_type($candidatePath);
        header('Content-Type: ' . $mimeType);

        $fileSize = filesize($candidatePath);

        if ($fileSize !== false) {
            header('Content-Length: ' . (string) $fileSize);
        }

        readfile($candidatePath);

        return true;
    }
}

require __DIR__ . '/public/index.php';