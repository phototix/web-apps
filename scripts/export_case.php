#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

function case_exports_storage_dir(): string
{
    return dirname(__DIR__) . '/storage/case-exports';
}

function case_exports_temp_dir(int $exportId): string
{
    $stamp = date('Ymd_His');
    return case_exports_storage_dir() . '/tmp/export_' . $exportId . '_' . $stamp;
}

function case_exports_ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Failed to create directory: ' . $path);
        }
    }
}

function case_exports_sanitize_filename(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'file';
    }
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    return trim($name, '_');
}

function case_exports_unique_path(string $dir, string $filename): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $extSuffix = $ext !== '' ? '.' . $ext : '';
    $candidate = $dir . '/' . $filename;
    $counter = 1;
    while (file_exists($candidate)) {
        $candidate = $dir . '/' . $base . '_' . $counter . $extSuffix;
        $counter++;
    }
    return $candidate;
}

function case_exports_download_file(string $url, string $destPath): void
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'follow_location' => 1,
        ],
        'https' => [
            'timeout' => 20,
            'follow_location' => 1,
        ]
    ]);

    $read = @fopen($url, 'rb', false, $context);
    if ($read === false) {
        throw new RuntimeException('Unable to open media URL: ' . $url);
    }

    $write = @fopen($destPath, 'wb');
    if ($write === false) {
        fclose($read);
        throw new RuntimeException('Unable to write media file: ' . $destPath);
    }

    stream_copy_to_stream($read, $write);
    fclose($read);
    fclose($write);
}

function case_exports_remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            case_exports_remove_dir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function case_exports_delete_notifications(PDO $pdo, int $userId, string $groupId): void
{
    $stmt = $pdo->prepare('
        DELETE FROM realtime_updates
        WHERE user_id = :user_id
          AND entity_id = :entity_id
          AND update_type IN ("case_export_ready", "case_export_failed")
    ');
    $stmt->execute(['user_id' => $userId, 'entity_id' => $groupId]);
}

function case_exports_insert_notification(PDO $pdo, int $userId, string $groupId, string $updateType, array $data, string $expiresAt): void
{
    $stmt = $pdo->prepare('
        INSERT INTO realtime_updates
        (user_id, update_type, entity_id, data, created_at, expires_at)
        VALUES (:user_id, :update_type, :entity_id, :data, NOW(), :expires_at)
    ');
    $stmt->execute([
        'user_id' => $userId,
        'update_type' => $updateType,
        'entity_id' => $groupId,
        'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'expires_at' => $expiresAt,
    ]);
}

function case_exports_fetch_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function case_exports_run(int $exportId): void
{
    $pdo = app_db();
    $stmt = $pdo->prepare('SELECT * FROM case_exports WHERE id = :id');
    $stmt->execute(['id' => $exportId]);
    $export = $stmt->fetch();
    if (!$export) {
        throw new RuntimeException('Export record not found.');
    }

    $userId = (int) $export['user_id'];
    $sessionId = (int) $export['session_id'];
    $groupId = (string) $export['group_id'];
    $groupName = (string) ($export['group_name'] ?? '');
    $expiresAt = (string) ($export['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+30 days')));

    $user = case_exports_fetch_user($pdo, $userId) ?? ['id' => $userId];

    $updateStmt = $pdo->prepare('UPDATE case_exports SET status = :status WHERE id = :id');
    $updateStmt->execute(['status' => 'processing', 'id' => $exportId]);

    case_exports_delete_notifications($pdo, $userId, $groupId);

    $storageDir = case_exports_storage_dir();
    case_exports_ensure_dir($storageDir);

    $tempDir = case_exports_temp_dir($exportId);
    case_exports_ensure_dir($tempDir);
    $mediaDir = $tempDir . '/media';
    case_exports_ensure_dir($mediaDir);

    $conversationPath = $tempDir . '/conversation.txt';
    $dataPath = $tempDir . '/data.txt';
    $manifestPath = $tempDir . '/media_manifest.txt';

    $conversationHandle = fopen($conversationPath, 'wb');
    $dataHandle = fopen($dataPath, 'wb');
    $manifestHandle = fopen($manifestPath, 'wb');

    if (!$conversationHandle || !$dataHandle || !$manifestHandle) {
        throw new RuntimeException('Unable to create export files.');
    }

    $messageStmt = $pdo->prepare('
        SELECT sender_name, sender_number, message_type, content, data, media_url,
               media_caption, caption, message_id, media_type, timestamp
        FROM group_messages
        WHERE session_id = :session_id AND group_id = :group_id
        ORDER BY timestamp ASC
    ');
    $messageStmt->execute([
        'session_id' => $sessionId,
        'group_id' => $groupId,
    ]);

    while ($row = $messageStmt->fetch()) {
        $timestampMs = (int) ($row['timestamp'] ?? 0);
        $timestamp = $timestampMs > 0
            ? date('Y-m-d H:i:s', (int) floor($timestampMs / 1000))
            : date('Y-m-d H:i:s');

        $sender = trim((string) ($row['sender_name'] ?? ''));
        if ($sender === '') {
            $sender = trim((string) ($row['sender_number'] ?? ''));
        }
        if ($sender === '') {
            $sender = 'Unknown';
        }

        $content = trim((string) ($row['content'] ?? ''));
        if ($content === '') {
            $content = trim((string) ($row['media_caption'] ?? ''));
        }
        if ($content === '') {
            $content = trim((string) ($row['caption'] ?? ''));
        }
        if ($content === '') {
            $content = '[' . ((string) ($row['message_type'] ?? 'message')) . ']';
        }

        fwrite($conversationHandle, $timestamp . ' - ' . $sender . ': ' . $content . "\n");

        $dataText = trim((string) ($row['data'] ?? ''));
        if ($dataText !== '') {
            $messageId = (string) ($row['message_id'] ?? '');
            fwrite($dataHandle, $timestamp . ' | ' . $sender . ' | ' . $messageId . ' | ' . $dataText . "\n");
        }

        $mediaUrl = trim((string) ($row['media_url'] ?? ''));
        if ($mediaUrl !== '') {
            $urlPath = parse_url($mediaUrl, PHP_URL_PATH);
            $baseName = $urlPath ? basename((string) $urlPath) : '';
            if ($baseName === '' || $baseName === '.' || $baseName === '/') {
                $baseName = 'media_' . ((string) ($row['message_id'] ?? uniqid('media_', true)));
            }

            $baseName = case_exports_sanitize_filename($baseName);
            $extension = pathinfo($baseName, PATHINFO_EXTENSION);
            if ($extension === '') {
                $mediaType = (string) ($row['media_type'] ?? '');
                if (str_contains($mediaType, '/')) {
                    $parts = explode('/', $mediaType);
                    $extension = $parts[1] ?? '';
                }
                if ($extension !== '') {
                    $baseName .= '.' . case_exports_sanitize_filename($extension);
                }
            }

            $destPath = case_exports_unique_path($mediaDir, $baseName);
            $finalName = basename($destPath);
            try {
                case_exports_download_file($mediaUrl, $destPath);
                fwrite($manifestHandle, $finalName . ' | ' . $mediaUrl . "\n");
            } catch (Throwable $e) {
                fwrite($manifestHandle, $finalName . ' | ' . $mediaUrl . ' | failed: ' . $e->getMessage() . "\n");
            }
        }
    }

    fclose($conversationHandle);
    fclose($dataHandle);
    fclose($manifestHandle);

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive extension is not available.');
    }

    $safeGroup = $groupName !== '' ? $groupName : $groupId;
    $safeGroup = case_exports_sanitize_filename($safeGroup);
    $zipFilename = 'case_' . $safeGroup . '_' . $exportId . '_' . date('Ymd_His') . '.zip';
    $zipPath = $storageDir . '/' . $zipFilename;

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create zip file.');
    }

    $zip->addFile($conversationPath, 'conversation.txt');
    $zip->addFile($dataPath, 'data.txt');
    $zip->addFile($manifestPath, 'media_manifest.txt');

    $mediaFiles = scandir($mediaDir);
    if (is_array($mediaFiles)) {
        foreach ($mediaFiles as $mediaFile) {
            if ($mediaFile === '.' || $mediaFile === '..') {
                continue;
            }
            $mediaPath = $mediaDir . '/' . $mediaFile;
            if (is_file($mediaPath)) {
                $zip->addFile($mediaPath, 'media/' . $mediaFile);
            }
        }
    }

    $zip->close();

    $fileSize = is_file($zipPath) ? filesize($zipPath) : null;

    $updateStmt = $pdo->prepare('
        UPDATE case_exports
        SET status = :status,
            zip_path = :zip_path,
            zip_filename = :zip_filename,
            file_size = :file_size,
            completed_at = NOW(),
            error_message = NULL
        WHERE id = :id
    ');
    $updateStmt->execute([
        'status' => 'ready',
        'zip_path' => $zipPath,
        'zip_filename' => $zipFilename,
        'file_size' => $fileSize,
        'id' => $exportId,
    ]);

    $baseUrl = app_env('APP_PUBLIC_URL');
    if ($baseUrl === null || $baseUrl === '') {
        $baseUrl = app_env('APP_BASE_URL', 'http://localhost');
    }
    $baseUrl = rtrim($baseUrl, '/');
    $downloadUrl = $baseUrl . '/api/cases/exports/' . $exportId . '/download';

    $notificationData = [
        'export_id' => $exportId,
        'group_id' => $groupId,
        'group_name' => $groupName,
        'download_url' => $downloadUrl,
        'expires_at' => $expiresAt,
    ];

    case_exports_delete_notifications($pdo, $userId, $groupId);
    case_exports_insert_notification($pdo, $userId, $groupId, 'case_export_ready', $notificationData, $expiresAt);

    app_log_audit('case_export_ready', [
        'export_id' => $exportId,
        'group_id' => $groupId,
        'group_name' => $groupName,
        'session_id' => $sessionId,
        'zip_filename' => $zipFilename,
        'file_size' => $fileSize,
    ], $user);

    case_exports_remove_dir($tempDir);
}

function case_exports_fail(int $exportId, string $message, ?array $export = null): void
{
    $pdo = app_db();
    $updateStmt = $pdo->prepare('
        UPDATE case_exports
        SET status = :status,
            error_message = :error,
            completed_at = NOW()
        WHERE id = :id
    ');
    $updateStmt->execute([
        'status' => 'failed',
        'error' => $message,
        'id' => $exportId,
    ]);

    if ($export === null) {
        $stmt = $pdo->prepare('SELECT * FROM case_exports WHERE id = :id');
        $stmt->execute(['id' => $exportId]);
        $export = $stmt->fetch() ?: null;
    }

    if ($export) {
        $userId = (int) $export['user_id'];
        $groupId = (string) $export['group_id'];
        $groupName = (string) ($export['group_name'] ?? '');
        $sessionId = (int) $export['session_id'];
        $expiresAt = (string) ($export['expires_at'] ?? date('Y-m-d H:i:s', strtotime('+30 days')));

        $user = case_exports_fetch_user($pdo, $userId) ?? ['id' => $userId];

        $notificationData = [
            'export_id' => $exportId,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'error_message' => $message,
            'expires_at' => $expiresAt,
        ];

        case_exports_delete_notifications($pdo, $userId, $groupId);
        case_exports_insert_notification($pdo, $userId, $groupId, 'case_export_failed', $notificationData, $expiresAt);

        app_log_audit('case_export_failed', [
            'export_id' => $exportId,
            'group_id' => $groupId,
            'group_name' => $groupName,
            'session_id' => $sessionId,
            'error_message' => $message,
        ], $user);
    }
}

try {
    $exportId = isset($argv[1]) ? (int) $argv[1] : 0;
    if ($exportId <= 0) {
        throw new RuntimeException('Export ID is required.');
    }

    case_exports_run($exportId);
} catch (Throwable $e) {
    $exportId = isset($exportId) ? (int) $exportId : 0;
    if ($exportId > 0) {
        case_exports_fail($exportId, $e->getMessage());
    }
    app_log('Case export failed: ' . $e->getMessage(), 'ERROR');
    exit(1);
}
