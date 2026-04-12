-- ============================================================
-- Schema: adminchat database
--
-- This file creates all tables required for the admin-chat
-- module (www/wwwroot/digitallami.com/api/).
--
-- Run once on the "adminchat" database:
--   mysql -u<user> -p adminchat < adminchat_schema.sql
--
-- Or use the bundled import script which handles everything:
--   bash import_databases.sh
--
-- Default admin credentials after import:
--   Email   : admin@example.com
--   Password: admin123
-- ============================================================

SET SQL_MODE   = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone  = '+00:00';
SET NAMES utf8mb4;

-- ── users ────────────────────────────────────────────────────
-- Admin / agent accounts for the memorial-chat back-office.
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT            NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(100)   NOT NULL,
  `email`        VARCHAR(255)   NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `avatar_url`   VARCHAR(500)   DEFAULT NULL,
  `role`         ENUM('admin','agent') DEFAULT 'agent',
  `status`       ENUM('active','inactive') DEFAULT 'active',
  `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default admin account.
-- Password hash is for "admin123" (bcrypt, cost 10).
INSERT IGNORE INTO `users`
  (`id`, `username`, `email`, `password_hash`, `role`, `status`)
VALUES
  (1, 'admin', 'admin@example.com',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
   'admin', 'active');

-- ── memorial_profiles ────────────────────────────────────────
-- Profiles that can be shared inside chats.
CREATE TABLE IF NOT EXISTS `memorial_profiles` (
  `id`               VARCHAR(50)  NOT NULL,
  `name`             VARCHAR(200) NOT NULL,
  `avatar_url`       VARCHAR(500) DEFAULT NULL,
  `match_percentage` INT          NOT NULL DEFAULT 0,
  `membership_status` VARCHAR(50) DEFAULT 'free',
  `status`           VARCHAR(50)  DEFAULT 'newProfile',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── chats ────────────────────────────────────────────────────
-- One row per active chat session between an agent and a contact.
CREATE TABLE IF NOT EXISTS `chats` (
  `id`               VARCHAR(50)  NOT NULL,
  `name`             VARCHAR(200) NOT NULL,
  `contact_id`       VARCHAR(100) DEFAULT NULL,
  `avatar_url`       VARCHAR(500) DEFAULT NULL,
  `last_message`     TEXT,
  `last_message_time` VARCHAR(50) DEFAULT NULL,
  `is_pinned`        TINYINT(1)   NOT NULL DEFAULT 0,
  `is_unread`        TINYINT(1)   NOT NULL DEFAULT 0,
  `is_group`         TINYINT(1)   NOT NULL DEFAULT 0,
  `has_file`         TINYINT(1)   NOT NULL DEFAULT 0,
  `membership_status` VARCHAR(50) DEFAULT 'free',
  `assigned_to`      INT          DEFAULT NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chats_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_chats_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── messages ─────────────────────────────────────────────────
-- Individual messages within a chat session.
CREATE TABLE IF NOT EXISTS `messages` (
  `id`               VARCHAR(100) NOT NULL,
  `chat_id`          VARCHAR(50)  NOT NULL,
  `sender_id`        INT          DEFAULT NULL,
  `sender_type`      ENUM('agent','contact') NOT NULL DEFAULT 'agent',
  `text_content`     TEXT,
  `message_type`     VARCHAR(50)  NOT NULL DEFAULT 'text',
  `shared_profile_id` VARCHAR(50) DEFAULT NULL,
  `is_read`          TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_chat_id`     (`chat_id`),
  KEY `idx_messages_sender_id`   (`sender_id`),
  KEY `idx_messages_created_at`  (`created_at`),
  CONSTRAINT `fk_messages_chat_id`          FOREIGN KEY (`chat_id`)          REFERENCES `chats`            (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_sender_id`        FOREIGN KEY (`sender_id`)        REFERENCES `users`            (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_messages_shared_profile`   FOREIGN KEY (`shared_profile_id`) REFERENCES `memorial_profiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── profile_shares ───────────────────────────────────────────
-- Tracks profiles that have been shared inside chat sessions.
CREATE TABLE IF NOT EXISTS `profile_shares` (
  `id`         INT         NOT NULL AUTO_INCREMENT,
  `chat_id`    VARCHAR(50) NOT NULL,
  `profile_id` VARCHAR(50) NOT NULL,
  `shared_by`  INT         DEFAULT NULL,
  `status`     VARCHAR(50) NOT NULL DEFAULT 'sent',
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_profile_shares_chat_id`    (`chat_id`),
  KEY `idx_profile_shares_profile_id` (`profile_id`),
  CONSTRAINT `fk_profile_shares_chat`    FOREIGN KEY (`chat_id`)    REFERENCES `chats`            (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_shares_profile` FOREIGN KEY (`profile_id`) REFERENCES `memorial_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_shares_user`    FOREIGN KEY (`shared_by`)  REFERENCES `users`            (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
