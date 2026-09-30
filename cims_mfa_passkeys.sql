-- ============================================================
-- CIMS — MULTI-FACTOR AUTHENTICATION & WEBAUTHN PASSKEYS SCHEMA
-- GB Construction & Enterprise Smart Inventory System
-- ============================================================

-- 1. Add Multi-Factor Authentication columns to `users` table
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `mfa_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`,
  ADD COLUMN IF NOT EXISTS `mfa_secret` VARCHAR(64) NULL DEFAULT NULL AFTER `mfa_enabled`,
  ADD COLUMN IF NOT EXISTS `mfa_backup_codes` TEXT NULL DEFAULT NULL AFTER `mfa_secret`;

-- 2. Create `user_passkeys` table for FIDO2 / WebAuthn Biometric authenticators
CREATE TABLE IF NOT EXISTS `user_passkeys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `credential_id` VARCHAR(255) NOT NULL UNIQUE,
  `public_key` TEXT NOT NULL,
  `device_name` VARCHAR(100) DEFAULT 'Biometric Device',
  `sign_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  CONSTRAINT `fk_passkeys_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
