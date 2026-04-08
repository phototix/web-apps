#!/usr/bin/env php
<?php

declare(strict_types=1);

// Sync Groups Script
// Run this script hourly to sync groups from WhatsApp

require_once dirname(__DIR__) . '/app/bootstrap.php';

function sync_groups(): array {
    $result = [
        'synced_sessions' => 0,
        'synced_groups' => 0,
        'failed_sessions' => 0,
        'timestamp' => time()
    ];
    
    $pdo = app_db();
    
    try {
        // Get all active sessions that haven't been synced in the last hour
        $stmt = $pdo->prepare("
            SELECT * FROM whatsapp_sessions 
            WHERE status = 'active' 
            AND (updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR) OR updated_at IS NULL)
            ORDER BY updated_at ASC
            LIMIT 20
        ");
        $stmt->execute();
        $sessions = $stmt->fetchAll();
        
        foreach ($sessions as $session) {
            try {
                $syncResult = app_whatsapp_sync_groups($session['id']);
                $result['synced_groups'] += $syncResult['synced'] ?? 0;
                $result['synced_sessions']++;
                
                // Update session timestamp
                $stmt = $pdo->prepare("
                    UPDATE whatsapp_sessions 
                    SET updated_at = NOW() 
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $session['id']]);
                
            } catch (Exception $e) {
                app_log('Failed to sync groups for session: ' . $e->getMessage(), 'ERROR', [
                    'session_id' => $session['id'],
                    'session_name' => $session['session_name']
                ]);
                $result['failed_sessions']++;
            }
        }
        
    } catch (Exception $e) {
        app_log('Group sync script error: ' . $e->getMessage(), 'ERROR');
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Run sync
$result = sync_groups();

// Log result
app_log('Group sync completed', 'INFO', $result);

// Output result for cron logging
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;