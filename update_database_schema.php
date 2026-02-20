<?php
/**
 * Database Schema Update Script
 * 
 * This script executes ALTER TABLE commands to add missing columns and tables
 * to fix SQLSTATE[42S22] "Column not found" errors.
 * 
 * Run this script ONCE after deploying the consolidated schema files.
 * 
 * Usage: php update_database_schema.php
 */

require_once __DIR__ . '/includes/database.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "==============================================\n";
echo "Database Schema Update Script\n";
echo "==============================================\n\n";

// Track success/failure
$success_count = 0;
$error_count = 0;
$errors = [];

/**
 * Execute a SQL statement safely
 */
function executeSql($pdo, $sql, $description) {
    global $success_count, $error_count, $errors;
    
    try {
        echo "Executing: $description\n";
        $pdo->exec($sql);
        echo "✓ SUCCESS: $description\n\n";
        $success_count++;
        return true;
    } catch (PDOException $e) {
        // Ignore "Duplicate column" and "Table already exists" errors
        if (strpos($e->getMessage(), 'Duplicate column') !== false || 
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠ SKIPPED: $description (already exists)\n\n";
            $success_count++;
            return true;
        }
        
        echo "✗ ERROR: $description\n";
        echo "   Message: " . $e->getMessage() . "\n\n";
        $error_count++;
        $errors[] = [
            'description' => $description,
            'error' => $e->getMessage()
        ];
        return false;
    }
}

try {
    // ============================================
    // USER DATABASE UPDATES (dbs15253086)
    // ============================================
    echo "--- USER DATABASE UPDATES ---\n";
    
    $user_db = Database::getUserDB();
    
    // Add azure_roles column to users table
    executeSql(
        $user_db,
        "ALTER TABLE users ADD COLUMN azure_roles JSON DEFAULT NULL COMMENT 'Original Microsoft Entra ID roles from Azure AD authentication'",
        "Add azure_roles column to users table"
    );
    
    // Add azure_oid column to users table
    executeSql(
        $user_db,
        "ALTER TABLE users ADD COLUMN azure_oid VARCHAR(255) DEFAULT NULL COMMENT 'Azure Object Identifier (OID) from Microsoft Entra ID authentication' AFTER azure_roles",
        "Add azure_oid column to users table"
    );
    
    // Add index for azure_oid
    executeSql(
        $user_db,
        "ALTER TABLE users ADD INDEX idx_azure_oid (azure_oid)",
        "Add index for azure_oid column"
    );
    
    // Add deleted_at column to users table
    executeSql(
        $user_db,
        "ALTER TABLE users ADD COLUMN deleted_at DATETIME DEFAULT NULL COMMENT 'Timestamp when the user was soft deleted (NULL = active)'",
        "Add deleted_at column to users table"
    );
    
    // Add index for deleted_at
    executeSql(
        $user_db,
        "ALTER TABLE users ADD INDEX idx_deleted_at (deleted_at)",
        "Add index for deleted_at column"
    );
    
    // Add last_reminder_sent_at column to users table
    executeSql(
        $user_db,
        "ALTER TABLE users ADD COLUMN last_reminder_sent_at DATETIME DEFAULT NULL COMMENT 'Timestamp when the last profile reminder email was sent to the user'",
        "Add last_reminder_sent_at column to users table"
    );
    
    // Add index for last_reminder_sent_at
    executeSql(
        $user_db,
        "ALTER TABLE users ADD INDEX idx_last_reminder_sent_at (last_reminder_sent_at)",
        "Add index for last_reminder_sent_at column"
    );
    
    // Add show_birthday column to users table
    executeSql(
        $user_db,
        "ALTER TABLE users ADD COLUMN show_birthday BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Whether to display birthday publicly on profile' AFTER birthday",
        "Add show_birthday column to users table"
    );
    
    // Add user_type column to users table
    executeSql(
        $user_db,
        "ALTER TABLE users ADD COLUMN user_type ENUM('member', 'guest') DEFAULT NULL COMMENT 'Microsoft Entra ID user type: member (internal) or guest (external/invited)' AFTER azure_oid",
        "Add user_type column to users table"
    );
    
    // Add current_session_id column to users table for single-session enforcement
    executeSql(
        $user_db,
        "ALTER TABLE users ADD COLUMN current_session_id VARCHAR(255) DEFAULT NULL COMMENT 'Active session ID for single-session enforcement; NULL if no active session'",
        "Add current_session_id column to users table"
    );

    // Drop invitation_tokens table (token-based registration removed)
    executeSql(
        $user_db,
        "DROP TABLE IF EXISTS invitation_tokens",
        "Drop invitation_tokens table"
    );
    
    // ============================================
    // CONTENT DATABASE UPDATES (dbs15161271)
    // ============================================
    echo "\n--- CONTENT DATABASE UPDATES ---\n";
    
    $content_db = Database::getContentDB();
    
    // Add first_name column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN first_name VARCHAR(100) DEFAULT NULL",
        "Add first_name column to alumni_profiles table"
    );
    
    // Add last_name column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN last_name VARCHAR(100) DEFAULT NULL",
        "Add last_name column to alumni_profiles table"
    );
    
    // Add secondary_email column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN secondary_email VARCHAR(255) DEFAULT NULL COMMENT 'Optional secondary email address for profile display only'",
        "Add secondary_email column to alumni_profiles table"
    );
    
    // Add mobile_phone column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN mobile_phone VARCHAR(50) DEFAULT NULL",
        "Add mobile_phone column to alumni_profiles table"
    );
    
    // Add linkedin_url column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN linkedin_url VARCHAR(255) DEFAULT NULL",
        "Add linkedin_url column to alumni_profiles table"
    );
    
    // Add xing_url column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN xing_url VARCHAR(255) DEFAULT NULL",
        "Add xing_url column to alumni_profiles table"
    );
    
    // Add industry column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN industry VARCHAR(100) DEFAULT NULL",
        "Add industry column to alumni_profiles table"
    );
    
    // Add company column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN company VARCHAR(255) DEFAULT NULL",
        "Add company column to alumni_profiles table"
    );
    
    // Add position column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN position VARCHAR(255) DEFAULT NULL",
        "Add position column to alumni_profiles table"
    );
    
    // Add study_program column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN study_program VARCHAR(255) DEFAULT NULL",
        "Add study_program column to alumni_profiles table"
    );
    
    // Add semester column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN semester VARCHAR(50) DEFAULT NULL",
        "Add semester column to alumni_profiles table"
    );
    
    // Add angestrebter_abschluss column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN angestrebter_abschluss VARCHAR(100) DEFAULT NULL",
        "Add angestrebter_abschluss column to alumni_profiles table"
    );
    
    // Add degree column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN degree VARCHAR(100) DEFAULT NULL",
        "Add degree column to alumni_profiles table"
    );
    
    // Add graduation_year column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN graduation_year INT DEFAULT NULL",
        "Add graduation_year column to alumni_profiles table"
    );
    
    // Add image_path column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN image_path VARCHAR(500) DEFAULT NULL",
        "Add image_path column to alumni_profiles table"
    );
    
    // Add last_verified_at column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN last_verified_at DATETIME DEFAULT NULL",
        "Add last_verified_at column to alumni_profiles table"
    );
    
    // Add last_reminder_sent_at column to alumni_profiles
    executeSql(
        $content_db,
        "ALTER TABLE alumni_profiles ADD COLUMN last_reminder_sent_at DATETIME DEFAULT NULL",
        "Add last_reminder_sent_at column to alumni_profiles table"
    );
    
    // Add microsoft_forms_url column to polls table
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD COLUMN microsoft_forms_url TEXT DEFAULT NULL COMMENT 'Microsoft Forms embed URL or direct link for external survey integration'",
        "Add microsoft_forms_url column to polls table"
    );
    
    // Add visible_to_all column to polls table
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD COLUMN visible_to_all BOOLEAN NOT NULL DEFAULT 0 COMMENT 'If true, show poll to all users regardless of roles'",
        "Add visible_to_all column to polls table"
    );
    
    // Add is_internal column to polls table
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD COLUMN is_internal BOOLEAN NOT NULL DEFAULT 1 COMMENT 'If true, hide poll after user votes. If false (external Forms), show hide button'",
        "Add is_internal column to polls table"
    );
    
    // Add allowed_roles column to polls table
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD COLUMN allowed_roles JSON DEFAULT NULL COMMENT 'JSON array of Entra roles that can see this poll (filters against user azure_roles)'",
        "Add allowed_roles column to polls table"
    );
    
    // Add target_groups column to polls table
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD COLUMN target_groups JSON DEFAULT NULL COMMENT 'JSON array of target groups (candidate, alumni_board, board, member, head)'",
        "Add target_groups column to polls table"
    );
    
    // Add is_active column to polls table
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT 1 COMMENT 'Flag to activate/deactivate poll display'",
        "Add is_active column to polls table"
    );
    
    // Add end_date column to polls table
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD COLUMN end_date DATETIME DEFAULT NULL COMMENT 'Poll expiration date'",
        "Add end_date column to polls table"
    );
    
    // Add index for is_active column
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD INDEX idx_is_active (is_active)",
        "Add index for is_active column"
    );
    
    // Add index for end_date column
    executeSql(
        $content_db,
        "ALTER TABLE polls ADD INDEX idx_end_date (end_date)",
        "Add index for end_date column"
    );
    
    // Create poll_hidden_by_user table
    $create_poll_hidden_table = "
    CREATE TABLE IF NOT EXISTS poll_hidden_by_user (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        poll_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_poll_user (poll_id, user_id),
        INDEX idx_poll_id (poll_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
      COMMENT='Tracks which users have manually hidden which polls'
    ";
    
    executeSql(
        $content_db,
        $create_poll_hidden_table,
        "Create poll_hidden_by_user table"
    );
    
    // Add sellers_data column to event_documentation table
    executeSql(
        $content_db,
        "ALTER TABLE event_documentation ADD COLUMN sellers_data JSON DEFAULT NULL COMMENT 'JSON array of seller entries with name, items, quantity, and revenue'",
        "Add sellers_data column to event_documentation table"
    );
    
    // Add sales_data column to event_documentation table
    executeSql(
        $content_db,
        "ALTER TABLE event_documentation ADD COLUMN sales_data JSON DEFAULT NULL COMMENT 'JSON array of sales entries with items and revenue'",
        "Add sales_data column to event_documentation table"
    );
    
    // Add calculations column to event_documentation table
    executeSql(
        $content_db,
        "ALTER TABLE event_documentation ADD COLUMN calculations TEXT DEFAULT NULL COMMENT 'Calculation notes and formulas'",
        "Add calculations column to event_documentation table"
    );
    
    // Add is_archived_in_easyverein column to inventory_items table
    executeSql(
        $content_db,
        "ALTER TABLE inventory_items ADD COLUMN is_archived_in_easyverein BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Flag indicating if item is archived in EasyVerein'",
        "Add is_archived_in_easyverein column to inventory_items table"
    );
    
    // Add index for is_archived_in_easyverein column
    executeSql(
        $content_db,
        "ALTER TABLE inventory_items ADD INDEX idx_is_archived_in_easyverein (is_archived_in_easyverein)",
        "Add index for is_archived_in_easyverein column"
    );
    
    // Add created_by and updated_by columns to event_documentation table
    executeSql(
        $content_db,
        "ALTER TABLE event_documentation ADD COLUMN created_by INT UNSIGNED DEFAULT NULL COMMENT 'User who created the documentation'",
        "Add created_by column to event_documentation table"
    );
    
    executeSql(
        $content_db,
        "ALTER TABLE event_documentation ADD COLUMN updated_by INT UNSIGNED DEFAULT NULL COMMENT 'User who last updated the documentation'",
        "Add updated_by column to event_documentation table"
    );
    
    // Add needs_helpers column to events table
    executeSql(
        $content_db,
        "ALTER TABLE events ADD COLUMN needs_helpers BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Flag indicating if the event needs helpers'",
        "Add needs_helpers column to events table"
    );
    
    // Add index for needs_helpers column
    executeSql(
        $content_db,
        "ALTER TABLE events ADD INDEX idx_needs_helpers (needs_helpers)",
        "Add index for needs_helpers column"
    );
    
    // Add contact_person column to events table
    executeSql(
        $content_db,
        "ALTER TABLE events ADD COLUMN contact_person VARCHAR(255) NULL COMMENT 'Contact person for the event'",
        "Add contact_person column to events table"
    );
    
    // Create event_financial_stats table
    $create_financial_stats_table = "
    CREATE TABLE IF NOT EXISTS event_financial_stats (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id INT UNSIGNED NOT NULL,
        category ENUM('Verkauf', 'Kalkulation') NOT NULL COMMENT 'Category: Sales or Calculation',
        item_name VARCHAR(255) NOT NULL COMMENT 'Item name, e.g., Brezeln, Äpfel, Grillstand',
        quantity INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Quantity sold or calculated',
        revenue DECIMAL(10, 2) DEFAULT NULL COMMENT 'Revenue in EUR (optional for calculations)',
        record_year YEAR NOT NULL COMMENT 'Year of record for historical comparison',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT UNSIGNED NOT NULL COMMENT 'User who created the record',
        
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        
        INDEX idx_event_id (event_id),
        INDEX idx_category (category),
        INDEX idx_record_year (record_year),
        INDEX idx_event_year (event_id, record_year),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
      COMMENT='Financial statistics for events - tracks sales and calculations with yearly comparison'
    ";
    
    executeSql(
        $content_db,
        $create_financial_stats_table,
        "Create event_financial_stats table"
    );
    
    // Create poll_options table
    $create_poll_options_table = "
    CREATE TABLE IF NOT EXISTS poll_options (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        poll_id INT UNSIGNED NOT NULL,
        option_text VARCHAR(500) NOT NULL COMMENT 'Text of the poll option',
        display_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Order in which options are displayed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
        
        INDEX idx_poll_id (poll_id),
        INDEX idx_display_order (display_order)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
      COMMENT='Options/choices for internal polls (not used for Microsoft Forms)'
    ";
    
    executeSql(
        $content_db,
        $create_poll_options_table,
        "Create poll_options table"
    );
    
    // Create poll_votes table
    $create_poll_votes_table = "
    CREATE TABLE IF NOT EXISTS poll_votes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        poll_id INT UNSIGNED NOT NULL,
        option_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
        
        UNIQUE KEY unique_poll_user_vote (poll_id, user_id),
        INDEX idx_poll_id (poll_id),
        INDEX idx_option_id (option_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
      COMMENT='User votes on poll options (not used for Microsoft Forms)'
    ";
    
    executeSql(
        $content_db,
        $create_poll_votes_table,
        "Create poll_votes table"
    );
    
    // Create event_registrations table
    $create_event_registrations_table = "
    CREATE TABLE IF NOT EXISTS event_registrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        status ENUM('confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
        registered_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        
        UNIQUE KEY unique_event_user_registration (event_id, user_id),
        INDEX idx_event_id (event_id),
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
      COMMENT='Simple event registrations (alternative to event_signups with slots)'
    ";
    
    executeSql(
        $content_db,
        $create_event_registrations_table,
        "Create event_registrations table"
    );
    
    // Create system_logs table
    $create_system_logs_table = "
    CREATE TABLE IF NOT EXISTS system_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL COMMENT 'User who performed the action (0 for system/cron)',
        action VARCHAR(100) NOT NULL COMMENT 'Action type (e.g., login_success, invitation_created)',
        entity_type VARCHAR(100) DEFAULT NULL COMMENT 'Type of entity affected (e.g., user, event, cron)',
        entity_id INT UNSIGNED DEFAULT NULL COMMENT 'ID of affected entity',
        details TEXT DEFAULT NULL COMMENT 'Additional details in text or JSON format',
        ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the user',
        user_agent TEXT DEFAULT NULL COMMENT 'User agent string',
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_entity_type (entity_type),
        INDEX idx_entity_id (entity_id),
        INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
      COMMENT='System-wide audit log for tracking all user and system actions'
    ";
    
    executeSql(
        $content_db,
        $create_system_logs_table,
        "Create system_logs table"
    );
    
    // Create links table
    $create_links_table = "
    CREATE TABLE IF NOT EXISTS links (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        url VARCHAR(500) NOT NULL,
        description VARCHAR(500) DEFAULT NULL,
        icon VARCHAR(100) DEFAULT 'fas fa-link',
        sort_order INT UNSIGNED DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT UNSIGNED DEFAULT NULL COMMENT 'User ID who created the link',
        INDEX idx_sort_order (sort_order)
    ) ENGINE=InnoDB
      DEFAULT CHARSET=utf8mb4
      COLLATE=utf8mb4_unicode_ci
      COMMENT='Useful links for quick access to frequently used tools and resources'
    ";
    
    executeSql(
        $content_db,
        $create_links_table,
        "Create links table"
    );
    
    // ============================================
    // INVENTORY & RENTALS SCHEMA UPDATES
    // ============================================
    echo "\n--- INVENTORY & RENTALS SCHEMA UPDATES ---\n";
    
    // Add quantity_borrowed column to inventory_items
    executeSql(
        $content_db,
        "ALTER TABLE inventory_items ADD COLUMN quantity_borrowed INT NOT NULL DEFAULT 0 COMMENT 'Number of items currently borrowed/checked out' AFTER quantity",
        "Add quantity_borrowed column to inventory_items table"
    );
    
    // Add purpose column to rentals
    executeSql(
        $content_db,
        "ALTER TABLE rentals ADD COLUMN purpose VARCHAR(255) DEFAULT NULL COMMENT 'Purpose of the rental' AFTER amount",
        "Add purpose column to rentals table"
    );
    
    // Add destination column to rentals
    executeSql(
        $content_db,
        "ALTER TABLE rentals ADD COLUMN destination VARCHAR(255) DEFAULT NULL COMMENT 'Destination/location where item is used' AFTER purpose",
        "Add destination column to rentals table"
    );
    
    // Add checkout_date column to rentals
    executeSql(
        $content_db,
        "ALTER TABLE rentals ADD COLUMN checkout_date DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Date when item was checked out' AFTER destination",
        "Add checkout_date column to rentals table"
    );
    
    // Add status column to rentals
    executeSql(
        $content_db,
        "ALTER TABLE rentals ADD COLUMN status ENUM('active', 'returned', 'defective') NOT NULL DEFAULT 'active' COMMENT 'Rental status' AFTER actual_return",
        "Add status column to rentals table"
    );
    
    // Add defect_notes column to rentals
    executeSql(
        $content_db,
        "ALTER TABLE rentals ADD COLUMN defect_notes TEXT AFTER notes",
        "Add defect_notes column to rentals table"
    );
    
    // Add index for status in rentals
    executeSql(
        $content_db,
        "ALTER TABLE rentals ADD INDEX idx_status (status)",
        "Add index for status column in rentals table"
    );
    
    // Make expected_return nullable (was NOT NULL before)
    executeSql(
        $content_db,
        "ALTER TABLE rentals MODIFY COLUMN expected_return DATE DEFAULT NULL",
        "Make expected_return nullable in rentals table"
    );
    
    // Update inventory_history change_type enum to include checkout/checkin/writeoff
    executeSql(
        $content_db,
        "ALTER TABLE inventory_history MODIFY COLUMN change_type ENUM('add', 'remove', 'adjust', 'sync', 'checkout', 'checkin', 'writeoff') NOT NULL",
        "Update change_type enum in inventory_history table"
    );
    
    // ============================================
    // SUMMARY
    // ============================================
    echo "==============================================\n";
    echo "SUMMARY\n";
    echo "==============================================\n";
    echo "Successful operations: $success_count\n";
    echo "Failed operations: $error_count\n";
    
    if ($error_count > 0) {
        echo "\n--- ERRORS ---\n";
        foreach ($errors as $error) {
            echo "- {$error['description']}: {$error['error']}\n";
        }
        echo "\n";
        exit(1);
    } else {
        echo "\n✓ All schema updates completed successfully!\n";
        echo "The database schema is now up to date.\n";
        exit(0);
    }
    
} catch (Exception $e) {
    echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
