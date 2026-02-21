-- ================================================
-- Content Database: links table
-- ================================================
CREATE TABLE IF NOT EXISTS `links` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `url` VARCHAR(2048) NOT NULL,
  `icon` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
