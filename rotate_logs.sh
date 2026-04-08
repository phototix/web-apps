#!/bin/bash

# Log rotation script for ERP ezy.chat application logs
# Run daily via cron: 0 0 * * * /var/www/html/erp.ezy.chat/rotate_logs.sh

LOG_DIR="/var/www/html/erp.ezy.chat/logs"
RETENTION_DAYS=14

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR"

# Compress logs older than 1 day
find "$LOG_DIR" -name "*.log" -mtime +1 -type f ! -name "*.gz" -exec gzip {} \;

# Remove compressed logs older than retention period
find "$LOG_DIR" -name "*.log.gz" -mtime +$RETENTION_DAYS -type f -delete

# Create empty log file for today if it doesn't exist
TODAY_LOG="$LOG_DIR/$(date +%Y-%m-%d).log"
if [ ! -f "$TODAY_LOG" ]; then
    touch "$TODAY_LOG"
    chown www-data:www-data "$TODAY_LOG"
    chmod 644 "$TODAY_LOG"
fi

echo "Log rotation completed: $(date)"