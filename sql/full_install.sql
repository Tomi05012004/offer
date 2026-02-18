-- ================================================
-- FULL DATABASE INSTALLATION SCRIPT
-- ================================================
-- This script creates ALL tables for the complete system
-- including: Users, Authentication, Events, Projects, 
-- Blog, Inventory, Polls, Alumni, Invoices, Links, Mail Queue
-- ================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ================================================
-- USER DATABASE TABLES (dbs15253086)
-- ================================================

-- ================================================
-- TABLE: users
-- ================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100),
  `last_name` VARCHAR(100),
  `birthday` DATE DEFAULT NULL,
  `show_birthday` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Whether to display birthday publicly on profile',
  `about_me` TEXT DEFAULT NULL COMMENT 'User biography or about me section',
  `gender` VARCHAR(50) DEFAULT NULL COMMENT 'User gender',
  `role` ENUM('user', 'admin', 'moderator') NOT NULL DEFAULT 'user',
  `azure_roles` JSON DEFAULT NULL COMMENT 'Original Microsoft Entra ID roles from Azure AD authentication',
  `azure_oid` VARCHAR(255) DEFAULT NULL COMMENT 'Azure Object Identifier (OID) from Microsoft Entra ID authentication',
  `microsoft_id` VARCHAR(255) DEFAULT NULL COMMENT 'Microsoft account identifier for OAuth authentication',
  `job_title` VARCHAR(255) DEFAULT NULL COMMENT 'Job title from Microsoft Entra ID',
  `company` VARCHAR(255) DEFAULT NULL COMMENT 'Company name from Microsoft Entra ID',
  `entra_roles` TEXT DEFAULT NULL COMMENT 'JSON array of Microsoft Entra role names for display',
  `profile_complete` TINYINT NOT NULL DEFAULT 0 COMMENT 'Flag to track if user has completed initial profile setup',
  `last_profile_update` DATETIME DEFAULT NULL COMMENT 'Timestamp of last profile update',
  `tfa_secret` VARCHAR(255) DEFAULT NULL COMMENT 'Two-factor authentication secret key',
  `two_factor_secret` VARCHAR(255) DEFAULT NULL COMMENT 'Alternative two-factor authentication secret key',
  `tfa_enabled` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Whether two-factor authentication is enabled',
  `two_factor_enabled` TINYINT NOT NULL DEFAULT 0 COMMENT 'Whether two-factor authentication is enabled (alternative column)',
  `is_alumni_validated` BOOLEAN NOT NULL DEFAULT 1 COMMENT 'Whether alumni user is validated by board (0=needs approval, 1=approved)',
  `notify_new_projects` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Receive email notifications for new projects',
  `notify_projects` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Receive email notifications for projects (alternative column)',
  `notify_new_events` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Receive email notifications for new events',
  `notify_events` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Receive email notifications for events (alternative column)',
  `theme_preference` ENUM('auto', 'light', 'dark') DEFAULT 'auto' COMMENT 'User interface theme preference',
  `deleted_at` DATETIME DEFAULT NULL COMMENT 'Timestamp when the user was soft deleted (NULL = active)',
  `last_reminder_sent_at` DATETIME DEFAULT NULL COMMENT 'Timestamp when the last profile reminder email was sent to the user',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_microsoft_id` (`microsoft_id`),
  INDEX `idx_deleted_at` (`deleted_at`),
  INDEX `idx_last_reminder_sent_at` (`last_reminder_sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User authentication and profile information';

-- ================================================
-- TABLE: user_sessions
-- ================================================
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `session_token` VARCHAR(255) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_session_token` (`session_token`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User session management';

-- ================================================
-- TABLE: login_attempts
-- ================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `success` BOOLEAN NOT NULL DEFAULT 0,
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_ip_address` (`ip_address`),
  INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track login attempts for security';

-- ================================================
-- TABLE: password_resets
-- ================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `reset_token` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `used` BOOLEAN NOT NULL DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_reset_token` (`reset_token`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Password reset token management';

-- ================================================
-- TABLE: email_change_requests
-- ================================================
CREATE TABLE IF NOT EXISTS `email_change_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `new_email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email change request token management';

-- ================================================
-- TABLE: invitation_tokens
-- ================================================
CREATE TABLE IF NOT EXISTS `invitation_tokens` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL,
  `role` ENUM('board_finance', 'board_internal', 'board_external', 'alumni_board', 'alumni_auditor', 'alumni', 'honorary_member', 'head', 'member', 'candidate') NOT NULL DEFAULT 'member',
  `created_by` INT UNSIGNED NOT NULL,
  `expires_at` TIMESTAMP NOT NULL,
  `used_at` TIMESTAMP NULL DEFAULT NULL,
  `used_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`used_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_token` (`token`),
  INDEX `idx_email` (`email`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Invitation tokens for user registration';

-- ================================================
-- TABLE: mail_queue
-- ================================================
CREATE TABLE IF NOT EXISTS `mail_queue` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `recipient_email` VARCHAR(255) NOT NULL COMMENT 'Email address of the recipient',
  `subject` VARCHAR(255) NOT NULL COMMENT 'Email subject line',
  `body` TEXT NOT NULL COMMENT 'Email body content (HTML or plain text)',
  `status` ENUM('pending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Email delivery status',
  `ics_content` TEXT DEFAULT NULL COMMENT 'Optional iCalendar (.ics) content for calendar invitations',
  `error_message` TEXT DEFAULT NULL COMMENT 'Error message if status is failed',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of send attempts',
  `sent_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When the email was successfully sent',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_recipient_email` (`recipient_email`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email queue for asynchronous email sending with calendar invitation support';

-- ================================================
-- CONTENT DATABASE TABLES (dbs15161271)
-- ================================================

-- ================================================
-- TABLE: events
-- ================================================
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `event_date` DATE NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL COMMENT 'Event location',
  `maps_link` TEXT DEFAULT NULL COMMENT 'Google Maps or location link',
  `start_time` DATETIME DEFAULT NULL COMMENT 'Event start date and time',
  `end_time` DATETIME DEFAULT NULL COMMENT 'Event end date and time',
  `registration_start` DATETIME DEFAULT NULL COMMENT 'When registration opens',
  `registration_end` DATETIME DEFAULT NULL COMMENT 'When registration closes',
  `status` ENUM('planned', 'open', 'closed', 'running', 'past') DEFAULT 'planned' COMMENT 'Event status',
  `needs_helpers` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Flag indicating if the event needs helpers',
  `contact_person` VARCHAR(255) NULL COMMENT 'Contact person for the event',
  `allowed_roles` JSON DEFAULT NULL COMMENT 'JSON array of roles allowed to view/register for this event',
  `locked_by` INT UNSIGNED DEFAULT NULL COMMENT 'User ID who locked the event for editing',
  `locked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When the event was locked',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NOT NULL,
  INDEX `idx_event_date` (`event_date`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_status` (`status`),
  INDEX `idx_needs_helpers` (`needs_helpers`),
  INDEX `idx_locked_by` (`locked_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: event_documentation
-- ================================================
CREATE TABLE IF NOT EXISTS `event_documentation` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `documentation` TEXT,
  `sellers_data` JSON DEFAULT NULL COMMENT 'JSON array of seller entries with name, items, quantity, and revenue',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: event_financial_stats
-- ================================================
CREATE TABLE IF NOT EXISTS `event_financial_stats` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `category` ENUM('Verkauf', 'Kalkulation') NOT NULL COMMENT 'Category: Sales or Calculation',
  `item_name` VARCHAR(255) NOT NULL COMMENT 'Item name, e.g., Brezeln, Äpfel, Grillstand',
  `quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Quantity sold or calculated',
  `revenue` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Revenue in EUR (optional for calculations)',
  `record_year` YEAR NOT NULL COMMENT 'Year of record for historical comparison',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NOT NULL COMMENT 'User who created the record',
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_event_id` (`event_id`),
  INDEX `idx_category` (`category`),
  INDEX `idx_record_year` (`record_year`),
  INDEX `idx_event_year` (`event_id`, `record_year`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Financial statistics for events - tracks sales and calculations with yearly comparison';

-- ================================================
-- TABLE: alumni_profiles
-- ================================================
CREATE TABLE IF NOT EXISTS `alumni_profiles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `secondary_email` VARCHAR(255) DEFAULT NULL COMMENT 'Optional secondary email address for profile display only',
  `mobile_phone` VARCHAR(50) DEFAULT NULL,
  `linkedin_url` VARCHAR(255) DEFAULT NULL,
  `xing_url` VARCHAR(255) DEFAULT NULL,
  `industry` VARCHAR(100) DEFAULT NULL,
  `company` VARCHAR(255) DEFAULT NULL,
  `position` VARCHAR(255) DEFAULT NULL,
  `study_program` VARCHAR(255) DEFAULT NULL,
  `semester` VARCHAR(50) DEFAULT NULL,
  `angestrebter_abschluss` VARCHAR(100) DEFAULT NULL,
  `degree` VARCHAR(100) DEFAULT NULL,
  `graduation_year` INT DEFAULT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `last_verified_at` DATETIME DEFAULT NULL,
  `last_reminder_sent_at` DATETIME DEFAULT NULL,
  `bio` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: polls
-- ================================================
CREATE TABLE IF NOT EXISTS `polls` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `microsoft_forms_url` TEXT DEFAULT NULL COMMENT 'Microsoft Forms embed URL or direct link for external survey integration',
  `target_groups` JSON DEFAULT NULL COMMENT 'JSON array of target groups (candidate, alumni_board, board, member, head)',
  `visible_to_all` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'If true, show poll to all users regardless of roles',
  `is_internal` BOOLEAN NOT NULL DEFAULT 1 COMMENT 'If true, hide poll after user votes. If false (external Forms), show hide button',
  `allowed_roles` JSON DEFAULT NULL COMMENT 'JSON array of Entra roles that can see this poll (filters against user azure_roles)',
  `is_active` BOOLEAN NOT NULL DEFAULT 1 COMMENT 'Flag to activate/deactivate poll display',
  `end_date` DATETIME DEFAULT NULL COMMENT 'Poll expiration date',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NOT NULL,
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_is_active` (`is_active`),
  INDEX `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: poll_hidden_by_user
-- ================================================
CREATE TABLE IF NOT EXISTS `poll_hidden_by_user` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `poll_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `hidden_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_poll_user` (`poll_id`, `user_id`),
  INDEX `idx_poll_id` (`poll_id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks which users have manually hidden which polls';

-- ================================================
-- TABLE: system_settings
-- ================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: event_roles
-- ================================================
CREATE TABLE IF NOT EXISTS `event_roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(255) NOT NULL COMMENT 'Role name/identifier',
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Roles/permissions associated with events';

-- ================================================
-- TABLE: event_helper_types
-- ================================================
CREATE TABLE IF NOT EXISTS `event_helper_types` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Types of helper roles needed for events';

-- ================================================
-- TABLE: event_slots
-- ================================================
CREATE TABLE IF NOT EXISTS `event_slots` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `helper_type_id` INT UNSIGNED NOT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `quantity_needed` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`helper_type_id`) REFERENCES `event_helper_types`(`id`) ON DELETE CASCADE,
  INDEX `idx_helper_type_id` (`helper_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Time slots for event helpers';

-- ================================================
-- TABLE: event_signups
-- ================================================
CREATE TABLE IF NOT EXISTS `event_signups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `slot_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'confirmed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`slot_id`) REFERENCES `event_slots`(`id`) ON DELETE SET NULL,
  INDEX `idx_event_id` (`event_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_slot_id` (`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User signups for event helper slots';

-- ================================================
-- TABLE: event_history
-- ================================================
CREATE TABLE IF NOT EXISTS `event_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `change_type` VARCHAR(100) NOT NULL,
  `change_details` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_event_id` (`event_id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for event changes';

-- ================================================
-- TABLE: projects
-- ================================================
CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `client_name` VARCHAR(255) DEFAULT NULL,
  `client_contact_details` TEXT DEFAULT NULL,
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `type` ENUM('internal', 'external') DEFAULT 'internal',
  `status` ENUM('draft', 'open', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft',
  `max_consultants` INT UNSIGNED DEFAULT 1,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `documentation` VARCHAR(500) DEFAULT NULL COMMENT 'Path to project documentation PDF',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Project management';

-- ================================================
-- TABLE: project_applications
-- ================================================
CREATE TABLE IF NOT EXISTS `project_applications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `motivation` TEXT,
  `experience_count` INT UNSIGNED DEFAULT 0,
  `status` ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User applications for projects';

-- ================================================
-- TABLE: project_assignments
-- ================================================
CREATE TABLE IF NOT EXISTS `project_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(100) DEFAULT 'consultant',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_project_user` (`project_id`, `user_id`),
  INDEX `idx_project_id` (`project_id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User assignments to projects';

-- ================================================
-- TABLE: blog_posts
-- ================================================
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `external_link` VARCHAR(500) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `author_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_author_id` (`author_id`),
  INDEX `idx_category` (`category`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Blog posts and news articles';

-- ================================================
-- TABLE: blog_likes
-- ================================================
CREATE TABLE IF NOT EXISTS `blog_likes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `blog_posts`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_post_user` (`post_id`, `user_id`),
  INDEX `idx_post_id` (`post_id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User likes on blog posts';

-- ================================================
-- TABLE: blog_comments
-- ================================================
CREATE TABLE IF NOT EXISTS `blog_comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `blog_posts`(`id`) ON DELETE CASCADE,
  INDEX `idx_post_id` (`post_id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comments on blog posts';

-- ================================================
-- TABLE: categories
-- ================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `color` VARCHAR(7) DEFAULT '#6D9744' COMMENT 'Hex color code for category',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Categories for inventory items';

-- ================================================
-- TABLE: locations
-- ================================================
CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `address` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Storage locations for inventory items';

-- ================================================
-- TABLE: inventory_items (inventory)
-- ================================================
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `location_id` INT UNSIGNED DEFAULT NULL,
  `quantity` INT NOT NULL DEFAULT 0 COMMENT 'Total available quantity',
  `quantity_borrowed` INT NOT NULL DEFAULT 0 COMMENT 'Number of items currently borrowed/checked out',
  `barcode` VARCHAR(255) DEFAULT NULL COMMENT 'Barcode or QR code for item identification',
  `min_stock` INT DEFAULT 0 COMMENT 'Minimum stock level for alerts',
  `unit` VARCHAR(50) DEFAULT 'Stück' COMMENT 'Unit of measurement',
  `unit_price` DECIMAL(10, 2) DEFAULT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `notes` TEXT,
  `serial_number` VARCHAR(255) DEFAULT NULL,
  `easyverein_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID from EasyVerein sync',
  `is_archived_in_easyverein` BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Flag indicating if item is archived in EasyVerein',
  `last_synced_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Last sync timestamp from EasyVerein',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`location_id`) REFERENCES `locations`(`id`) ON DELETE SET NULL,
  INDEX `idx_category_id` (`category_id`),
  INDEX `idx_location_id` (`location_id`),
  INDEX `idx_barcode` (`barcode`),
  INDEX `idx_easyverein_id` (`easyverein_id`),
  INDEX `idx_is_archived_in_easyverein` (`is_archived_in_easyverein`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Inventory items';

-- ================================================
-- TABLE: rentals
-- ================================================
CREATE TABLE IF NOT EXISTS `rentals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` INT UNSIGNED NOT NULL DEFAULT 1,
  `purpose` VARCHAR(255) DEFAULT NULL COMMENT 'Purpose of the rental',
  `destination` VARCHAR(255) DEFAULT NULL COMMENT 'Destination/location where item is used',
  `checkout_date` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Date when item was checked out',
  `expected_return` DATE DEFAULT NULL,
  `return_date` DATE DEFAULT NULL COMMENT 'Date when item was returned',
  `actual_return` DATE DEFAULT NULL,
  `status` ENUM('active', 'returned', 'overdue', 'defective') NOT NULL DEFAULT 'active' COMMENT 'Rental status',
  `notes` TEXT,
  `defect_notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_return_date` (`return_date`),
  INDEX `idx_actual_return` (`actual_return`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Item rentals/loans tracking';

-- ================================================
-- TABLE: inventory_history
-- ================================================
CREATE TABLE IF NOT EXISTS `inventory_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `change_type` ENUM('add', 'remove', 'adjust', 'sync', 'checkout', 'checkin', 'writeoff') NOT NULL,
  `old_stock` INT NOT NULL,
  `new_stock` INT NOT NULL,
  `change_amount` INT NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `comment` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE,
  INDEX `idx_item_id` (`item_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_change_type` (`change_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for inventory changes';

-- ================================================
-- TABLE: poll_options
-- ================================================
CREATE TABLE IF NOT EXISTS `poll_options` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `poll_id` INT UNSIGNED NOT NULL,
  `option_text` VARCHAR(500) NOT NULL COMMENT 'Text of the poll option',
  `display_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Order in which options are displayed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE,
  INDEX `idx_poll_id` (`poll_id`),
  INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Options/choices for internal polls (not used for Microsoft Forms)';

-- ================================================
-- TABLE: poll_votes
-- ================================================
CREATE TABLE IF NOT EXISTS `poll_votes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `poll_id` INT UNSIGNED NOT NULL,
  `option_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`option_id`) REFERENCES `poll_options`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_poll_user_vote` (`poll_id`, `user_id`),
  INDEX `idx_poll_id` (`poll_id`),
  INDEX `idx_option_id` (`option_id`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User votes on poll options (not used for Microsoft Forms)';

-- ================================================
-- TABLE: event_registrations
-- ================================================
CREATE TABLE IF NOT EXISTS `event_registrations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `status` ENUM('confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
  `registered_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_event_user_registration` (`event_id`, `user_id`),
  INDEX `idx_event_id` (`event_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Simple event registrations (alternative to event_signups with slots)';

-- ================================================
-- TABLE: system_logs
-- ================================================
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'User who performed the action (0 for system/cron)',
  `action` VARCHAR(100) NOT NULL COMMENT 'Action type (e.g., login_success, invitation_created)',
  `entity_type` VARCHAR(100) DEFAULT NULL COMMENT 'Type of entity affected (e.g., user, event, cron)',
  `entity_id` INT UNSIGNED DEFAULT NULL COMMENT 'ID of affected entity',
  `details` TEXT DEFAULT NULL COMMENT 'Additional details in text or JSON format',
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the user',
  `user_agent` TEXT DEFAULT NULL COMMENT 'User agent string',
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_entity_type` (`entity_type`),
  INDEX `idx_entity_id` (`entity_id`),
  INDEX `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='System-wide audit log for tracking all user and system actions';

-- ================================================
-- TABLE: useful_links
-- ================================================
CREATE TABLE IF NOT EXISTS `useful_links` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL COMMENT 'Link title or name',
  `url` VARCHAR(500) NOT NULL COMMENT 'URL of the link',
  `description` TEXT DEFAULT NULL COMMENT 'Link description or notes',
  `created_by` INT UNSIGNED NOT NULL COMMENT 'User ID who created the link',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Useful links and resources for users';

-- ================================================
-- INVOICE DATABASE TABLES (dbs15251284)
-- ================================================

-- ================================================
-- TABLE: invoices
-- ================================================
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT 'User who submitted the invoice',
  `description` TEXT NOT NULL COMMENT 'Invoice description',
  `amount` DECIMAL(10, 2) NOT NULL COMMENT 'Invoice amount in EUR',
  `file_path` VARCHAR(500) NOT NULL COMMENT 'Path to uploaded invoice file',
  `status` ENUM('pending', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'pending' COMMENT 'Invoice processing status',
  `rejection_reason` TEXT DEFAULT NULL COMMENT 'Reason for rejection if status is rejected',
  `paid_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when invoice was marked as paid',
  `paid_by_user_id` INT UNSIGNED DEFAULT NULL COMMENT 'User ID who marked invoice as paid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_paid_by_user_id` (`paid_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Invoice management table for expense reimbursement tracking';

COMMIT;

-- ================================================
-- INITIAL DATA: Admin User
-- ================================================
-- Insert an admin user for non-Microsoft login scenarios
-- Default password is 'admin123' (hashed with PASSWORD_DEFAULT)
-- NOTE: Change this password immediately after installation!

START TRANSACTION;

INSERT INTO `users` (
  `username`, 
  `email`, 
  `password`, 
  `first_name`, 
  `last_name`, 
  `role`, 
  `profile_complete`, 
  `tfa_enabled`,
  `two_factor_enabled`,
  `created_at`
) VALUES (
  'admin',
  'admin@example.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'System',
  'Administrator',
  'admin',
  1,
  0,
  0,
  CURRENT_TIMESTAMP
);

COMMIT;

-- ================================================
-- INITIAL DATA: Test Data for Useful Links
-- ================================================

START TRANSACTION;

-- Get the admin user ID for foreign key reference
SET @admin_user_id = (SELECT id FROM users WHERE username = 'admin' LIMIT 1);

INSERT INTO `useful_links` (`title`, `url`, `description`, `created_by`, `created_at`) VALUES
('GitHub Repository', 'https://github.com', 'Source code hosting and version control', @admin_user_id, CURRENT_TIMESTAMP),
('Stack Overflow', 'https://stackoverflow.com', 'Developer Q&A community', @admin_user_id, CURRENT_TIMESTAMP),
('PHP Documentation', 'https://www.php.net/docs.php', 'Official PHP documentation and manual', @admin_user_id, CURRENT_TIMESTAMP),
('MySQL Documentation', 'https://dev.mysql.com/doc/', 'Official MySQL documentation', @admin_user_id, CURRENT_TIMESTAMP),
('MDN Web Docs', 'https://developer.mozilla.org', 'Web technology documentation by Mozilla', @admin_user_id, CURRENT_TIMESTAMP);

COMMIT;

-- ================================================
-- END OF INSTALLATION SCRIPT
-- ================================================
-- Installation completed successfully!
-- 
-- IMPORTANT SECURITY NOTES:
-- 1. Change the admin password immediately after installation
-- 2. Update admin email to a real email address
-- 3. Remove or secure this file after installation
-- 4. Configure proper database credentials in your config files
-- 
-- Default Admin Credentials:
-- Username: admin
-- Password: admin123
-- Email: admin@example.com
-- ================================================
