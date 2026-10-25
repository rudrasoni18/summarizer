-- ============================================================
--  DocuMind — Dual-Mode PDF & Direct Text Summarizer
--  Database Schema  |  MySQL 8 / TiDB Cloud Serverless
-- ============================================================

CREATE DATABASE IF NOT EXISTS `pdf_ai_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pdf_ai_db`;

-- ------------------------------------------------------------
--  Table: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT            NOT NULL AUTO_INCREMENT,
  `full_name`     VARCHAR(100)   NOT NULL,
  `email`         VARCHAR(150)   NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)   NOT NULL,
  `role`          VARCHAR(50)    NOT NULL DEFAULT 'Student',
  `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Table: pdf_history
--  Dual-mode: stores records for both PDF uploads and
--  Direct Text Input sessions.
--  `pdf_name` holds the original filename (e.g. "notes.pdf")
--  or the literal string "Direct Text Input".
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pdf_history` (
  `id`             INT          NOT NULL AUTO_INCREMENT,
  `user_id`        INT          NOT NULL,
  `title`          VARCHAR(255) NOT NULL,
  `pdf_name`       VARCHAR(255) NOT NULL COMMENT 'Original filename or "Direct Text Input"',
  `category`       VARCHAR(100) NOT NULL DEFAULT 'Study',
  `extracted_text` LONGTEXT     NOT NULL COMMENT 'Raw PDF text or pasted text',
  `summary`        LONGTEXT     NOT NULL,
  `imp_questions`  LONGTEXT     DEFAULT NULL,
  `user_notes`     TEXT         DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ph_user_id`    (`user_id`),
  INDEX `idx_ph_category`   (`category`),
  INDEX `idx_ph_created_at` (`created_at`),
  CONSTRAINT `fk_ph_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
