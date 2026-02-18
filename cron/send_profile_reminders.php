<?php
/**
 * Profile Reminder Cron Script
 * 
 * Sends reminder emails to all users whose profile (updated_at) hasn't been updated in over 1 year.
 * Implements idempotency: only sends if last_reminder_sent_at is NULL or older than 13 months.
 * 
 * Usage: php cron/send_profile_reminders.php
 */

// Load required files
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/MailService.php';

// Output start message
echo "=== Profile Reminder Email Cron Job ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Get database connections
    $userDb = Database::getUserDB();
    $contentDb = Database::getContentDB();
    
    // Query to find users with outdated profiles
    // - updated_at older than 1 year (12 months)
    // - last_reminder_sent_at is NULL OR older than 13 months (spam protection)
    // - deleted_at IS NULL (exclude soft-deleted users)
    $stmt = $userDb->prepare("
        SELECT 
            u.id,
            u.email,
            u.updated_at,
            u.last_reminder_sent_at,
            ap.first_name,
            ap.last_name
        FROM users u
        LEFT JOIN " . DB_CONTENT_NAME . ".alumni_profiles ap ON u.id = ap.user_id
        WHERE u.updated_at < DATE_SUB(NOW(), INTERVAL 12 MONTH)
        AND (u.last_reminder_sent_at IS NULL OR u.last_reminder_sent_at < DATE_SUB(NOW(), INTERVAL 13 MONTH))
        AND u.deleted_at IS NULL
        ORDER BY u.updated_at ASC
    ");
    
    $stmt->execute();
    $outdatedUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalUsers = count($outdatedUsers);
    echo "Found {$totalUsers} user(s) with outdated profiles.\n\n";
    
    if ($totalUsers === 0) {
        echo "No profile reminder emails to send today.\n";
        echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
        exit(0);
    }
    
    $emailsSent = 0;
    $emailsFailed = 0;
    
    // Log cron execution start
    try {
        $stmt = $contentDb->prepare("INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([0, 'cron_profile_reminders', 'cron', null, "Started: Found {$totalUsers} user(s) with outdated profiles", 'cron', 'cron']);
    } catch (Exception $e) {
        // Ignore logging errors
    }
    
    // Loop through users and send profile reminder emails
    foreach ($outdatedUsers as $user) {
        $userId = $user['id'];
        $email = $user['email'];
        $firstName = $user['first_name'] ?? 'Mitglied';
        $lastName = $user['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        
        echo "Sending profile reminder to: {$fullName} ({$email})... ";
        
        // Create call-to-action button with link to profile page
        $profileLink = BASE_URL . '/pages/auth/profile.php';
        
        // Get complete HTML template using new method
        $htmlBody = MailService::getProfileReminderEmailTemplate($firstName, $profileLink);
        
        // Send email using MailService
        try {
            $success = MailService::sendEmail($email, 'Bitte aktualisiere dein IBC Profil', $htmlBody);
            
            if ($success) {
                // Update last_reminder_sent_at to prevent re-sending
                // Also update last_profile_update to TODAY to reset the counter
                $updateStmt = $userDb->prepare("UPDATE users SET last_reminder_sent_at = NOW(), last_profile_update = CURDATE() WHERE id = ?");
                $updateStmt->execute([$userId]);
                
                $emailsSent++;
                echo "SUCCESS\n";
            } else {
                $emailsFailed++;
                echo "FAILED\n";
            }
        } catch (Exception $e) {
            $emailsFailed++;
            echo "ERROR: " . $e->getMessage() . "\n";
        }
        
        // Small delay between emails to avoid overwhelming SMTP server
        usleep(100000); // 0.1 second delay
    }
    
    // Output summary
    echo "\n=== Summary ===\n";
    echo "Total users with outdated profiles: {$totalUsers}\n";
    echo "Emails sent successfully: {$emailsSent}\n";
    echo "Emails failed: {$emailsFailed}\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
    // Log cron execution completion
    try {
        $stmt = $contentDb->prepare("INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $logDetails = "Completed: Total={$totalUsers}, Sent={$emailsSent}, Failed={$emailsFailed}";
        $stmt->execute([0, 'cron_profile_reminders', 'cron', null, $logDetails, 'cron', 'cron']);
    } catch (Exception $e) {
        // Ignore logging errors
    }
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Log error
    try {
        if (!isset($contentDb)) {
            $contentDb = Database::getContentDB();
        }
        $stmt = $contentDb->prepare("INSERT INTO system_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([0, 'cron_profile_reminders', 'cron', null, "ERROR: " . $e->getMessage(), 'cron', 'cron']);
    } catch (Exception $logError) {
        // Ignore logging errors
    }
    
    exit(1);
}
