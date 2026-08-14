<?php
/**
 * CIMS - Lightweight Rate Limiting & Anti-Brute Force Engine
 * Zero external dependencies: uses PHP Session & atomic transient cache files.
 */

if (!function_exists('get_client_ip')) {
    function get_client_ip(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

/**
 * Check if an action is within allowed rate limits.
 *
 * @param string $actionKey Unique identifier for the action (e.g. 'login_admin', 'ai_chat')
 * @param int $maxAttempts Maximum allowed attempts in the time window
 * @param int $windowSeconds Duration of time window in seconds
 * @param bool $trackByIp Whether to track attempts by IP + filesystem (critical for unauthenticated login)
 * @return array ['allowed' => bool, 'retry_after' => int, 'remaining' => int]
 */
function check_rate_limit(string $actionKey, int $maxAttempts, int $windowSeconds, bool $trackByIp = false): array {
    $now = time();

    if ($trackByIp) {
        $ip = get_client_ip();
        $cacheDir = sys_get_temp_dir() . '/cims_rate_limits';
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $cacheFile = $cacheDir . '/rl_' . md5($ip . '_' . $actionKey) . '.json';

        $attempts = [];
        if (file_exists($cacheFile)) {
            $data = @json_decode(@file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $attempts = $data;
            }
        }

        // Clean expired timestamps
        $attempts = array_values(array_filter($attempts, fn($t) => ($now - $t) < $windowSeconds));

        if (count($attempts) >= $maxAttempts) {
            $oldestInWindow = min($attempts);
            $retryAfter = max(1, $windowSeconds - ($now - $oldestInWindow));
            return [
                'allowed' => false,
                'retry_after' => $retryAfter,
                'remaining' => 0
            ];
        }

        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => max(0, $maxAttempts - count($attempts))
        ];
    }

    // Session-based rate limit
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['cims_rate_limits'][$actionKey])) {
        $_SESSION['cims_rate_limits'][$actionKey] = [];
    }

    $attempts = array_values(array_filter(
        $_SESSION['cims_rate_limits'][$actionKey],
        fn($t) => ($now - $t) < $windowSeconds
    ));
    $_SESSION['cims_rate_limits'][$actionKey] = $attempts;

    if (count($attempts) >= $maxAttempts) {
        $oldestInWindow = min($attempts);
        $retryAfter = max(1, $windowSeconds - ($now - $oldestInWindow));
        return [
            'allowed' => false,
            'retry_after' => $retryAfter,
            'remaining' => 0
        ];
    }

    return [
        'allowed' => true,
        'retry_after' => 0,
        'remaining' => max(0, $maxAttempts - count($attempts))
    ];
}

/**
 * Record an attempt for rate limiting.
 */
function record_rate_limit_attempt(string $actionKey, bool $trackByIp = false): void {
    $now = time();

    if ($trackByIp) {
        $ip = get_client_ip();
        $cacheDir = sys_get_temp_dir() . '/cims_rate_limits';
        if (!file_exists($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $cacheFile = $cacheDir . '/rl_' . md5($ip . '_' . $actionKey) . '.json';

        $attempts = [];
        if (file_exists($cacheFile)) {
            $data = @json_decode(@file_get_contents($cacheFile), true);
            if (is_array($data)) {
                $attempts = $data;
            }
        }
        $attempts[] = $now;
        @file_put_contents($cacheFile, json_encode($attempts));
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['cims_rate_limits'][$actionKey][] = $now;
}

/**
 * Clear/reset rate limit attempts (e.g. on successful login).
 */
function clear_rate_limit(string $actionKey, bool $trackByIp = false): void {
    if ($trackByIp) {
        $ip = get_client_ip();
        $cacheFile = sys_get_temp_dir() . '/cims_rate_limits/rl_' . md5($ip . '_' . $actionKey) . '.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['cims_rate_limits'][$actionKey])) {
        unset($_SESSION['cims_rate_limits'][$actionKey]);
    }
}
