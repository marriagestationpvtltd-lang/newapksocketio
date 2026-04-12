-- ============================================================
-- Migration: 001 – Create admins table
--
-- Run this once on the "ms" database if the admins table does
-- not yet exist.  It is safe to run multiple times because of
-- the IF NOT EXISTS / INSERT IGNORE guards.
--
--   mysql -u<user> -p ms < 001_create_admins_table.sql
--
-- Default credentials after import:
--   Email   : admin@ms.com
--   Password: Admin@123
-- ============================================================

CREATE TABLE IF NOT EXISTS `admins` (
  `id`         int UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       varchar(100)    NOT NULL,
  `email`      varchar(150)    NOT NULL,
  `password`   varchar(255)    NOT NULL,
  `role`       enum('super_admin','admin') DEFAULT 'admin',
  `is_active`  tinyint(1)      DEFAULT '1',
  `last_login` datetime        DEFAULT NULL,
  `created_at` timestamp       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the default super-admin account.
-- Password hash is for "Admin@123" (bcrypt, cost 10).
INSERT IGNORE INTO `admins`
  (`id`, `name`, `email`, `password`, `role`, `is_active`)
VALUES
  (1, 'System Admin', 'admin@ms.com',
   '$2y$10$gFzOiJlud/nhFIKO97hU9Off.Bx8Jg7u8W4CIXypZW3WyyDerF40K',
   'super_admin', 1);
