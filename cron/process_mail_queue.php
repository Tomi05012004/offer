<?php
/**
 * Mass Mail Queue – Cron Script
 *
 * Processes the next pending batch (up to MAIL_BATCH_SIZE = 200) for any
 * paused mass-mail job whose next_run_at timestamp has been reached.
 *
 * Recommended cron schedule (every 5 minutes so jobs are picked up promptly
 * once the 60-minute window has elapsed):
 *   *\/5 * * * * php /path/to/offer/cron/process_mail_queue.php >> /path/to/logs/mail_queue.log 2>&1
 *
 * Usage: php cron/process_mail_queue.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../src/MailService.php';

// Reuse the placeholder helper defined in mass_invitations.php without loading
// the full page – duplicate it here as a standalone helper.
if (!function_exists('applyMailPlaceholders')) {
    function applyMailPlaceholders(string $body, string $firstName, string $lastName, string $eventName): string {
        $anrede = $firstName !== '' ? "Hallo $firstName" : 'Hallo';
        return str_replace(
            ['{Anrede}', '{Vorname}', '{Nachname}', '{Event_Name}'],
            [$anrede, $firstName, $lastName, $eventName],
            $body
        );
    }
}

/** Batch size must match MAIL_BATCH_SIZE in pages/admin/mass_invitations.php */
define('CRON_BATCH_SIZE', 200);

echo "=== Mass Mail Queue Cron ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = Database::getContentDB();

    // Find all paused jobs whose next_run_at has been reached
    $jobStmt = $db->query("
        SELECT * FROM mass_mail_jobs
        WHERE status = 'paused'
          AND next_run_at IS NOT NULL
          AND next_run_at <= NOW()
        ORDER BY next_run_at ASC
    ");
    $jobs = $jobStmt->fetchAll();

    if (empty($jobs)) {
        echo "No jobs ready for processing.\n";
        exit(0);
    }

    foreach ($jobs as $job) {
        $jobId = (int)$job['id'];
        echo "Processing job #{$jobId}: {$job['subject']}\n";

        // Fetch next pending batch
        // CRON_BATCH_SIZE is a trusted integer constant – safe to interpolate as LIMIT
        $pendingStmt = $db->prepare("
            SELECT * FROM mass_mail_recipients
            WHERE job_id = ? AND status = 'pending'
            LIMIT " . (int)CRON_BATCH_SIZE
        );
        $pendingStmt->execute([$jobId]);
        $batch = $pendingStmt->fetchAll();

        if (empty($batch)) {
            // No pending recipients – mark as completed
            $db->prepare("UPDATE mass_mail_jobs SET status = 'completed' WHERE id = ?")->execute([$jobId]);
            echo "  → No pending recipients; job marked as completed.\n";
            continue;
        }

        $sent   = 0;
        $failed = 0;
        $updStatus = $db->prepare("
            UPDATE mass_mail_recipients SET status = ?, processed_at = NOW() WHERE id = ?
        ");

        foreach ($batch as $r) {
            $firstName = $r['first_name'] ?? '';
            $lastName  = $r['last_name']  ?? '';
            $eventName = $job['event_name'] ?? '';
            $personalBody = applyMailPlaceholders($job['body_template'], $firstName, $lastName, $eventName);
            $sanitized = nl2br(htmlspecialchars($personalBody, ENT_QUOTES, 'UTF-8'));
            $entraNote = '<p style="margin-top:20px;padding:12px;background:#f0f4ff;border-left:4px solid #4f46e5;border-radius:4px;font-size:14px;">'
                . '<strong>Hinweis:</strong> Der Login ins IBC Intranet erfolgt ausschließlich über deinen Microsoft-Account (Entra ID). '
                . 'Bitte nutze die Schaltfläche „Mit Microsoft anmelden" auf der Login-Seite.</p>';
            $htmlBody = MailService::getTemplate(
                htmlspecialchars($job['subject'], ENT_QUOTES, 'UTF-8'),
                '<p class="email-text">' . $sanitized . '</p>' . $entraNote
            );

            if (MailService::sendEmail($r['email'], $job['subject'], $htmlBody)) {
                $sent++;
                $updStatus->execute(['sent', $r['id']]);
            } else {
                $failed++;
                $updStatus->execute(['failed', $r['id']]);
                error_log("process_mail_queue: failed to send to " . $r['email'] . " (job #{$jobId})");
            }
        }

        // Update counters and schedule next batch in 60 minutes
        $db->prepare("
            UPDATE mass_mail_jobs
            SET sent_count   = sent_count + ?,
                failed_count = failed_count + ?,
                next_run_at  = DATE_ADD(NOW(), INTERVAL 1 HOUR)
            WHERE id = ?
        ")->execute([$sent, $failed, $jobId]);

        // Check whether all recipients are now processed
        $cntStmt = $db->prepare("
            SELECT COUNT(*) FROM mass_mail_recipients WHERE job_id = ? AND status = 'pending'
        ");
        $cntStmt->execute([$jobId]);
        $remaining = (int)$cntStmt->fetchColumn();

        if ($remaining === 0) {
            $db->prepare("UPDATE mass_mail_jobs SET status = 'completed' WHERE id = ?")->execute([$jobId]);
            echo "  → Sent: {$sent}, Failed: {$failed}. Job completed.\n";
        } else {
            echo "  → Sent: {$sent}, Failed: {$failed}. Remaining: {$remaining}. Next run scheduled.\n";
        }
    }

} catch (Exception $e) {
    error_log("process_mail_queue cron error: " . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone at: " . date('Y-m-d H:i:s') . "\n";
exit(0);
