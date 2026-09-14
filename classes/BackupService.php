<?php
// =========================================================================
// SiteWare Enterprise Backup & Disaster Recovery Service
// Conforms to Quality Standards Rule 5 (Security), Rule 6 (OOP Architecture)
// and ISO 9001 (Clause 8.5.2 & 7.5) / ISO/IEC 25010 (Reliability & Recoverability)
// =========================================================================

class BackupService
{
    private PDO $pdo;
    private string $backupDir;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $this->pdo = $GLOBALS['pdo'];
        } else {
            throw new RuntimeException("Database connection is not available for BackupService.");
        }

        $this->backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
        $this->ensureBackupDirectoryExists();
    }

    /**
     * Ensure the backups directory exists and is secured against direct HTTP access.
     */
    private function ensureBackupDirectoryExists(): void
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        // Maintain security files in backups directory
        $htaccessPath = $this->backupDir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = "# Deny direct HTTP web access to backup files (Security Hardening)\n"
                . "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Order deny,allow\n"
                . "    Deny from all\n"
                . "</IfModule>\n";
            file_put_contents($htaccessPath, $htaccessContent);
        }

        $indexPath = $this->backupDir . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($indexPath)) {
            file_put_contents($indexPath, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><p>Directory access forbidden.</p></body></html>");
        }
    }

    /**
     * Get the absolute path to the backup storage directory.
     */
    public function getBackupDir(): string
    {
        return $this->backupDir;
    }

    /**
     * Generate a complete database SQL backup using pure PHP PDO.
     *
     * @param bool $saveToServer Whether to write file to backups/ directory
     * @param string $prefix File prefix (e.g. 'siteware_backup_' or 'siteware_auto_prerestore_backup_')
     * @return array Metadata about the generated backup
     * @throws Exception
     */
    public function generateDatabaseBackup(bool $saveToServer = true, string $prefix = 'siteware_backup_'): array
    {
        $startTime = microtime(true);
        $timestampStr = date('Y-m-d_His');
        $filename = $prefix . $timestampStr . '.sql';
        $filePath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        $fileHandle = fopen($filePath, 'w');
        if (!$fileHandle) {
            throw new RuntimeException("Failed to create backup file in storage directory. Check file write permissions.");
        }

        try {
            // 1. Fetch Database Name and Tables
            $dbNameStmt = $this->pdo->query("SELECT DATABASE()");
            $dbName = $dbNameStmt->fetchColumn() ?: 'construction_inventory';

            $tablesStmt = $this->pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

            // 2. Write Standardized SiteWare Backup Header
            $header = "-- ==========================================================\n"
                . "-- SiteWare Enterprise Database Backup\n"
                . "-- Application: SiteWare Inventory & Management System\n"
                . "-- Generated: " . date('Y-m-d H:i:s T') . "\n"
                . "-- Database: `" . addslashes($dbName) . "`\n"
                . "-- Total Tables: " . count($tables) . "\n"
                . "-- Architecture: Pure PDO Streamed Exporter (ISO 9001 Clause 7.5 Aligned)\n"
                . "-- ==========================================================\n\n"
                . "SET FOREIGN_KEY_CHECKS = 0;\n"
                . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
                . "SET time_zone = '+08:00';\n"
                . "SET NAMES utf8mb4;\n\n";
            fwrite($fileHandle, $header);

            $totalRowsExported = 0;

            // 3. Process each table
            foreach ($tables as $table) {
                // Table structure header
                $tableHeader = "-- ----------------------------------------------------------\n"
                    . "-- Table structure for table `{$table}`\n"
                    . "-- ----------------------------------------------------------\n"
                    . "DROP TABLE IF EXISTS `{$table}`;\n";
                fwrite($fileHandle, $tableHeader);

                // Create Table definition
                $createStmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
                $createRow = $createStmt->fetch(PDO::FETCH_NUM);
                if (!empty($createRow[1])) {
                    fwrite($fileHandle, $createRow[1] . ";\n\n");
                }

                // Dump table records in memory-safe chunks
                $countStmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
                $rowCount = (int)$countStmt->fetchColumn();

                if ($rowCount > 0) {
                    fwrite($fileHandle, "-- Dumping data for table `{$table}` (" . $rowCount . " records)\n");

                    $chunkSize = 300;
                    $offset = 0;

                    while ($offset < $rowCount) {
                        $dataStmt = $this->pdo->prepare("SELECT * FROM `{$table}` LIMIT :limit OFFSET :offset");
                        $dataStmt->bindValue(':limit', $chunkSize, PDO::PARAM_INT);
                        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                        $dataStmt->execute();
                        $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($rows)) {
                            $columnNames = array_keys($rows[0]);
                            $quotedColumns = array_map(fn($col) => "`{$col}`", $columnNames);
                            $insertPrefix = "INSERT INTO `{$table}` (" . implode(', ', $quotedColumns) . ") VALUES\n";
                            fwrite($fileHandle, $insertPrefix);

                            $valueSets = [];
                            foreach ($rows as $row) {
                                $escapedVals = [];
                                foreach ($row as $val) {
                                    if ($val === null) {
                                        $escapedVals[] = 'NULL';
                                    } elseif (is_numeric($val) && !is_string($val)) {
                                        $escapedVals[] = $val;
                                    } else {
                                        $escapedVals[] = $this->pdo->quote((string)$val);
                                    }
                                }
                                $valueSets[] = "  (" . implode(', ', $escapedVals) . ")";
                                $totalRowsExported++;
                            }

                            fwrite($fileHandle, implode(",\n", $valueSets) . ";\n\n");
                        }

                        $offset += $chunkSize;
                    }
                }
            }

            // 4. Footer constraints restore
            $footer = "\n-- ==========================================================\n"
                . "-- Re-enable Foreign Key Constraints\n"
                . "-- ==========================================================\n"
                . "SET FOREIGN_KEY_CHECKS = 1;\n"
                . "-- Backup completed successfully at " . date('Y-m-d H:i:s T') . "\n";
            fwrite($fileHandle, $footer);

        } finally {
            fclose($fileHandle);
        }

        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
        $duration = round(microtime(true) - $startTime, 3);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filePath,
            'filesize' => $fileSize,
            'filesize_formatted' => $this->formatBytes($fileSize),
            'total_tables' => count($tables),
            'total_rows' => $totalRowsExported,
            'duration_seconds' => $duration,
            'created_at' => date('Y-m-d H:i:s'),
            'type' => str_starts_with($filename, 'siteware_auto_prerestore_') ? 'auto_snapshot' : 'manual_backup'
        ];
    }

    /**
     * Trigger an automated security incident snapshot when an attack or brute-force is detected.
     * Enforces an anti-spam cooldown window (default 30 minutes) to prevent disk-exhaustion attacks.
     *
     * @param string $reason Description of detected attack
     * @param string $sourceIp Attacking client IP
     * @param int $cooldownSeconds Anti-spam throttle window
     * @return array|null Metadata of backup if created, null if throttled
     */
    public function triggerSecurityIncidentSnapshot(string $reason = 'Brute-force attack detected', string $sourceIp = '127.0.0.1', int $cooldownSeconds = 1800): ?array
    {
        $cooldownFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'siteware_sec_snapshot_lock.json';
        $now = time();

        if (file_exists($cooldownFile)) {
            $lockData = @json_decode(@file_get_contents($cooldownFile), true);
            if (is_array($lockData) && isset($lockData['last_triggered'])) {
                if (($now - (int)$lockData['last_triggered']) < $cooldownSeconds) {
                    // Throttled: snapshot was already taken recently during this attack window
                    return null;
                }
            }
        }

        // Generate immediate security snapshot
        $backupMeta = $this->generateDatabaseBackup(true, 'siteware_security_snapshot_');

        // Record cooldown lock
        @file_put_contents($cooldownFile, json_encode([
            'last_triggered' => $now,
            'reason' => $reason,
            'source_ip' => $sourceIp,
            'filename' => $backupMeta['filename']
        ]));

        return $backupMeta;
    }

    /**
     * Run an automated daily database backup if one hasn't been taken today.
     * Also prunes automated daily backups older than $retentionDays.
     *
     * @param int $retentionDays Number of days of daily backups to preserve (default 14)
     * @return array|null Metadata of generated backup, or null if today's backup already exists
     */
    public function runScheduledDailyBackup(int $retentionDays = 14): ?array
    {
        $todayPrefix = 'siteware_daily_' . date('Y-m-d');
        $existingDaily = glob($this->backupDir . DIRECTORY_SEPARATOR . $todayPrefix . '*.sql');

        $generated = null;
        if (empty($existingDaily)) {
            $generated = $this->generateDatabaseBackup(true, 'siteware_daily_');
        }

        // Prune older automated daily backups past retention threshold
        $this->pruneDailyBackups($retentionDays);

        return $generated;
    }

    /**
     * Prune automated daily backup files older than a specified number of days.
     * Standard manual backups and security incident snapshots are NEVER automatically deleted.
     *
     * @param int $retentionDays
     * @return int Number of pruned files
     */
    public function pruneDailyBackups(int $retentionDays = 14): int
    {
        $this->ensureBackupDirectoryExists();
        $files = glob($this->backupDir . DIRECTORY_SEPARATOR . 'siteware_daily_*.sql');
        if (empty($files)) return 0;

        $pruneThreshold = time() - ($retentionDays * 86400);
        $prunedCount = 0;

        foreach ($files as $file) {
            if (filemtime($file) < $pruneThreshold) {
                if (@unlink($file)) {
                    $prunedCount++;
                }
            }
        }

        return $prunedCount;
    }

    /**
     * List all available backup files stored on the server.
     *
     * @return array List of backup records sorted newest first
     */
    public function listBackups(): array
    {
        $this->ensureBackupDirectoryExists();
        $files = glob($this->backupDir . DIRECTORY_SEPARATOR . '*.sql');
        $backups = [];

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $mtime = filemtime($file);

            $isSecuritySnapshot = str_starts_with($filename, 'siteware_security_snapshot_');
            $isPreRestore = str_starts_with($filename, 'siteware_auto_prerestore_');
            $isDaily = str_starts_with($filename, 'siteware_daily_');

            if ($isSecuritySnapshot) {
                $typeLabel = 'Security Incident Snapshot';
                $badgeClass = 'bg-danger text-white';
            } elseif ($isPreRestore) {
                $typeLabel = 'Pre-Restore Snapshot';
                $badgeClass = 'bg-warning text-dark';
            } elseif ($isDaily) {
                $typeLabel = 'Automated Daily Backup';
                $badgeClass = 'bg-info text-dark';
            } else {
                $typeLabel = 'Standard Backup';
                $badgeClass = 'bg-primary text-white';
            }

            $backups[] = [
                'filename' => $filename,
                'filesize' => $size,
                'filesize_formatted' => $this->formatBytes($size),
                'created_at' => date('Y-m-d H:i:s', $mtime),
                'created_at_relative' => function_exists('time_elapsed_string') ? time_elapsed_string(date('Y-m-d H:i:s', $mtime)) : date('M d, Y h:i A', $mtime),
                'timestamp' => $mtime,
                'is_auto_snapshot' => ($isPreRestore || $isDaily || $isSecuritySnapshot),
                'type_label' => $typeLabel,
                'badge_class' => $badgeClass
            ];
        }

        // Sort descending by modification time (newest first)
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Delete a specific backup file by filename.
     *
     * @param string $filename Base filename of the backup
     * @return bool
     * @throws Exception
     */
    public function deleteBackup(string $filename): bool
    {
        $safeName = basename($filename);
        if (!str_ends_with(strtolower($safeName), '.sql')) {
            throw new InvalidArgumentException("Invalid backup file format. Only .sql files can be managed.");
        }

        $fullPath = $this->backupDir . DIRECTORY_SEPARATOR . $safeName;
        if (!file_exists($fullPath)) {
            throw new RuntimeException("The requested backup file does not exist.");
        }

        if (!unlink($fullPath)) {
            throw new RuntimeException("Failed to delete the backup file. Check file permissions.");
        }

        return true;
    }

    /**
     * Restore database from a SQL backup file.
     * Automatically creates a pre-restore safety snapshot before applying changes.
     *
     * @param string $filePath Full path to the SQL backup file
     * @param string|null $displayName Name to record in summary
     * @return array
     * @throws Exception
     */
    public function restoreFromBackup(string $filePath, ?string $displayName = null): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("Backup file not found or is unreadable.");
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            throw new RuntimeException("Backup file is empty (0 bytes). Cannot restore.");
        }

        // Validate basic SQL content
        $sample = file_get_contents($filePath, false, null, 0, 1024);
        if ($sample === false || (stripos($sample, 'CREATE TABLE') === false && stripos($sample, 'INSERT INTO') === false && stripos($sample, 'SiteWare') === false)) {
            throw new RuntimeException("The selected file does not appear to be a valid SiteWare or MySQL database backup.");
        }

        // 1. AUTOMATED SAFETY PRE-RESTORE SNAPSHOT (ISO 9001 Risk Mitigation & ISO/IEC 25010 Recoverability)
        $snapshotMeta = $this->generateDatabaseBackup(true, 'siteware_auto_prerestore_backup_');

        $startTime = microtime(true);
        $executedQueries = 0;

        // 2. Read and execute statements with foreign key checks disabled
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException("Could not open backup file for reading.");
        }

        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $this->pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");

        try {
            $buffer = '';
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                // Skip comments and empty lines
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                $buffer .= $line;

                // If statement ends with semicolon
                if (str_ends_with(rtrim($line), ';')) {
                    $query = trim($buffer);
                    if ($query !== '') {
                        $this->pdo->exec($query);
                        $executedQueries++;
                    }
                    $buffer = '';
                }
            }

            // Execute any trailing statement in buffer
            if (trim($buffer) !== '') {
                $this->pdo->exec(trim($buffer));
                $executedQueries++;
            }

        } catch (Throwable $e) {
            throw new RuntimeException("Restore halted due to SQL execution error: " . $e->getMessage());
        } finally {
            fclose($handle);
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        }

        $duration = round(microtime(true) - $startTime, 3);

        return [
            'success' => true,
            'filename' => $displayName ?: basename($filePath),
            'queries_executed' => $executedQueries,
            'duration_seconds' => $duration,
            'safety_snapshot' => $snapshotMeta['filename'],
            'restored_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Download a backup file securely via HTTP stream.
     *
     * @param string $filename Base filename of the backup
     * @throws Exception
     */
    public function downloadBackup(string $filename): void
    {
        $safeName = basename($filename);
        if (!str_ends_with(strtolower($safeName), '.sql')) {
            throw new InvalidArgumentException("Invalid backup file requested.");
        }

        $fullPath = $this->backupDir . DIRECTORY_SEPARATOR . $safeName;
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            throw new RuntimeException("The requested backup file does not exist or cannot be read.");
        }

        // Clean any output buffer before streaming
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fullPath));

        readfile($fullPath);
        exit;
    }

    /**
     * Format byte values to human readable sizes (B, KB, MB, GB).
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);
        return round($value, $precision) . ' ' . $units[$power];
    }
}
