#!/usr/bin/env php
<?php

declare(strict_types=1);

// Cleanup Old Data Script
// Run this script daily to remove old data

require_once dirname(__DIR__) . '/app/bootstrap.php';

function cleanup_old_data(): array {
    $result = [
        'deleted_messages' => 0,
        'deleted_updates' => 0,
        'cleaned_events' => 0,
        'deleted_invites' => 0,
        'deleted_case_exports' => 0,
        'timestamp' => time()
    ];
    
    $pdo = app_db();
    
    try {
        // 1. Delete messages older than retention period based on user tier
        // Get message retention days for each tier
        $tierRetention = [
            'basic' => 7,
            'business' => 30,
            'enterprise' => 90
        ];
        
        foreach ($tierRetention as $tier => $days) {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            $stmt = $pdo->prepare("
                DELETE gm FROM group_messages gm
                JOIN whatsapp_groups wg ON gm.group_id = wg.id
                JOIN whatsapp_sessions ws ON wg.session_id = ws.id
                JOIN users u ON ws.user_id = u.id
                WHERE u.tier = :tier 
                AND gm.created_at < :cutoff
            ");
            
            $stmt->execute(['tier' => $tier, 'cutoff' => $cutoffDate]);
            $result['deleted_messages'] += $stmt->rowCount();
            $stmt->closeCursor();
        }
        
        // 2. Delete expired real-time updates
        $stmt = $pdo->prepare("DELETE FROM realtime_updates WHERE expires_at <= NOW()");
        $stmt->execute();
        $result['deleted_updates'] = $stmt->rowCount();
        $stmt->closeCursor();
        
        // 3. Cleanup old webhook events (keep for 30 days for debugging)
        $stmt = $pdo->prepare("
            DELETE FROM webhook_events_queue 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status IN ('completed', 'failed')
        ");
        $stmt->execute();
        $result['cleaned_events'] = $stmt->rowCount();
        $stmt->closeCursor();
        
        // 4. Delete expired invites
        $stmt = $pdo->prepare("DELETE FROM user_invites WHERE expires_at <= NOW() AND used_at IS NULL");
        $stmt->execute();
        $result['deleted_invites'] = $stmt->rowCount();
        $stmt->closeCursor();

        // 5. Cleanup expired case exports and files (30 days retention)
        $exportStmt = $pdo->prepare("SELECT id, zip_path FROM case_exports WHERE expires_at <= NOW()");
        $exportStmt->execute();
        $exports = $exportStmt->fetchAll();
        $exportStmt->closeCursor();
        foreach ($exports as $export) {
            $zipPath = $export['zip_path'] ?? '';
            if ($zipPath && is_file($zipPath)) {
                @unlink($zipPath);
            }
        }
        $deleteExportsStmt = $pdo->prepare("DELETE FROM case_exports WHERE expires_at <= NOW()");
        $deleteExportsStmt->execute();
        $result['deleted_case_exports'] = $deleteExportsStmt->rowCount();
        $deleteExportsStmt->closeCursor();

        // 6. Optimize tables
        $tables = ['group_messages', 'realtime_updates', 'webhook_events_queue', 'case_exports'];
        foreach ($tables as $table) {
            $optStmt = $pdo->query("OPTIMIZE TABLE {$table}");
            if ($optStmt instanceof PDOStatement) {
                $optStmt->fetchAll();
                $optStmt->closeCursor();
            }
        }
        
    } catch (Exception $e) {
        app_log('Cleanup script error: ' . $e->getMessage(), 'ERROR');
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Run cleanup
$result = cleanup_old_data();

// Log result
app_log('Data cleanup completed', 'INFO', $result);

// Output result for cron logging
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
