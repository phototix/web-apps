#!/usr/bin/env php
<?php

declare(strict_types=1);

// WhatsApp Webhook Processing Script
// Run this script as a cron job every minute

require_once dirname(__DIR__) . '/app/bootstrap.php';

function process_webhook_queue(): array {
    $result = [
        'processed' => 0,
        'failed' => 0,
        'timestamp' => time()
    ];
    
    try {
        // Process webhook events
        $events = app_get_pending_webhook_events(10);
        
        foreach ($events as $event) {
            try {
                // Mark as processing
                app_mark_webhook_event_processing($event['id']);
                
                // Process the event
                app_process_webhook_event($event);
                
                // Mark as completed
                app_mark_webhook_event_completed($event['id']);
                $result['processed']++;
                
            } catch (Exception $e) {
                app_log('Failed to process webhook event: ' . $e->getMessage(), 'ERROR', [
                    'event_id' => $event['id'],
                    'event_type' => $event['event_type']
                ]);
                
                app_mark_webhook_event_failed($event['id']);
                $result['failed']++;
            }
        }
        
        // Cleanup old real-time updates
        $cleaned = app_cleanup_old_updates();
        $result['cleaned_updates'] = $cleaned;
        
    } catch (Exception $e) {
        app_log('Webhook processing script error: ' . $e->getMessage(), 'ERROR');
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Run processing
$result = process_webhook_queue();

// Log result
app_log('Webhook processing completed', 'INFO', $result);

// Output result for cron logging
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;