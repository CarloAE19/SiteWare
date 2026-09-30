<?php
/**
 * SiteWare — Construction Inventory Management System (CIMS)
 * Copyright (c) GB Construction & Enterprises Inc. & The MedYas.
 * All Rights Reserved.
 *
 * PROPRIETARY AND CONFIDENTIAL.
 * Module: RFC 6238 TOTP Multi-Factor Authentication Helper
 */

class TotpHelper
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure Base32 secret key.
     * Default 160 bits (32 base32 characters) for high security.
     */
    public static function generateSecret(int $length = 32): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $secret = '';
        $max = strlen($alphabet) - 1;
        $randomBytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[ord($randomBytes[$i]) % ($max + 1)];
        }
        return $secret;
    }

    /**
     * Decode a Base32 string into raw binary.
     */
    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);
        if (empty($b32)) {
            return '';
        }

        $chars = self::BASE32_ALPHABET;
        $buffer = 0;
        $bufferSize = 0;
        $binary = '';

        for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
            $char = $b32[$i];
            $val = strpos($chars, $char);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $binary .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Calculate a 6-digit TOTP code for a given timestamp and secret.
     */
    public static function calculateCode(string $secret, ?int $timestamp = null, int $timeStep = 30): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $secretBin = self::base32Decode($secret);
        if (empty($secretBin)) {
            return '000000';
        }

        $timeSlice = floor($timestamp / $timeStep);
        // Pack into 64-bit big-endian binary counter
        $packedCounter = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1 calculation (RFC 6238 / RFC 4226)
        $hash = hash_hmac('sha1', $packedCounter, $secretBin, true);

        // Dynamic truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $code = $truncatedHash % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifies a 6-digit code against the secret key.
     * Allows a discrepancy window (default ±1 window = 30s before and after) to compensate for minor clock drift.
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1, int $timeStep = 30): bool
    {
        $code = trim($code);
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return false;
        }

        $now = time();
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $checkTime = $now + ($i * $timeStep);
            $calculated = self::calculateCode($secret, $checkTime, $timeStep);
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate standard otpauth:// URL for authenticator apps.
     */
    public static function getOtpAuthUri(string $username, string $secret, string $issuer = 'SiteWare-CIMS'): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedUser = rawurlencode($username);
        return "otpauth://totp/{$encodedIssuer}:{$encodedUser}?secret={$secret}&issuer={$encodedIssuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate 8 cryptographically secure single-use backup recovery codes.
     * Returns:
     * [
     *    'plain' => ['A1B2-C3D4', ...],
     *    'hashed' => ['$2y$12$...', ...]
     * ]
     */
    public static function generateBackupCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate 8 alphanumeric characters (uppercase)
            $bytes = random_bytes(4);
            $hex = strtoupper(bin2hex($bytes));
            // Format as XXXX-XXXX for readability
            $formatted = substr($hex, 0, 4) . '-' . substr($hex, 4, 4);

            $plain[] = $formatted;
            $hashed[] = password_hash($formatted, PASSWORD_DEFAULT);
        }

        return [
            'plain' => $plain,
            'hashed' => $hashed
        ];
    }

    /**
     * Verifies a submitted backup code against stored hashed codes.
     * If valid, returns the matching array index (so the caller can remove it and update DB).
     * If invalid, returns null.
     */
    public static function verifyAndConsumeBackupCode(string $submittedCode, array $hashedCodes): ?int
    {
        $submittedCode = strtoupper(trim($submittedCode));
        // Remove spaces or hyphens for lenient user entry
        $cleanedSubmitted = str_replace(['-', ' '], '', $submittedCode);

        foreach ($hashedCodes as $index => $hash) {
            // Check direct match
            if (password_verify($submittedCode, $hash)) {
                return $index;
            }
            // Check formatted with hyphen if user omitted hyphen
            if (strlen($cleanedSubmitted) === 8) {
                $hyphenated = substr($cleanedSubmitted, 0, 4) . '-' . substr($cleanedSubmitted, 4, 4);
                if (password_verify($hyphenated, $hash)) {
                    return $index;
                }
            }
        }

        return null;
    }
}
