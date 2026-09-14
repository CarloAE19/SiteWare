<?php
// =========================================================================
// SiteWare Automated Daily Backup & Retention CLI Runner
// Can be scheduled via Windows Task Scheduler or Linux crontab
// Example Windows Task: C:\xampp\php\php.exe c:\xampp\htdocs\CIMS\scripts\auto_backup.php
// =========================================================================

if (php_sapi_name() !== 'cli' && !isset($_SERVER['SHELL'])) {
    // Only allow command line execution or internal server crons
    http_response_code(403);
    echo "Access denied: CLI only.";
    exit(1);
}

require_once dirname(__DIR__) . '/Connection/db.php';
require_once dirname(__DIR__) . '/classes/BackupService.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting SiteWare Automated Daily Backup Runner...\n";

try {
    $backupService = new BackupService($pdo);
    
    // Retention period in days (default: 14 days)
    $retentionDays = 14;

    $result = $backupService->runScheduledDailyBackup($retentionDays);

    if ($result !== null) {
        echo "[" . date('Y-m-d H:i:s') . "] SUCCESS: Generated daily backup: {$result['filename']} ({$result['filesize_formatted']})\n";
        echo " - Tables Exported: {$result['total_tables']}\n";
        echo " - Total Records: {$result['total_rows']}\n";
        echo " - Duration: {$result['duration_seconds']}s\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] NOTICE: Today's daily backup has already been generated. Skipping generation.\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Backup check and retention policy complete.\n";
    exit(0);

} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: Failed to execute automated daily backup: " . $e->getMessage() . "\n";
    exit(1);
}
