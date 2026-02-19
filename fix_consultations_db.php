<?php
/**
 * Database Migration: Fix consultations system
 * - Rename topic_category → topic
 * - Add sender_id, sender_role to consultation_replies
 * - Fix status enum to include 'answered'
 */
require_once 'includes/config.php';

$queries = [
    // 1. Rename topic_category → topic (if topic_category exists)
    "ALTER TABLE `consultations` CHANGE COLUMN `topic_category` `topic` varchar(100) NOT NULL",

    // 2. Add sender_id column to consultation_replies (if not exists)
    "ALTER TABLE `consultation_replies` ADD COLUMN IF NOT EXISTS `sender_id` int(11) DEFAULT NULL",

    // 3. Add sender_role column to consultation_replies (if not exists)
    "ALTER TABLE `consultation_replies` ADD COLUMN IF NOT EXISTS `sender_role` enum('student','teacher') DEFAULT 'teacher'",

    // 4. Add created_at to consultation_replies if missing (some queries reference it)
    "ALTER TABLE `consultation_replies` ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp()",

    // 5. Add message column alias (consultation_replies uses 'message' but table has 'reply_message')
    // We'll handle this by renaming reply_message → message for consistency
    "ALTER TABLE `consultation_replies` CHANGE COLUMN `reply_message` `message` text NOT NULL",

    // 6. Modify status enum to include 'answered' as alias for resolved
    "ALTER TABLE `consultations` MODIFY COLUMN `status` enum('pending','processing','resolved','answered') NOT NULL DEFAULT 'pending'",
];

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Fix Consultations DB</title></head><body style='font-family:sans-serif;padding:20px;'>";
echo "<h2>🔧 Fixing Consultations Database...</h2>";

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ OK: " . htmlspecialchars(substr($sql, 0, 80)) . "...</p>";
    } else {
        // Check if it's a non-critical error (e.g., column already exists or already renamed)
        $error = $conn->error;
        if (strpos($error, 'Duplicate column') !== false || strpos($error, 'Unknown column') !== false) {
            echo "<p style='color: orange;'>⚠️ Skipped (already done): " . htmlspecialchars(substr($sql, 0, 80)) . "...</p>";
        } else {
            echo "<p style='color: red;'>❌ Error: $error<br>Query: " . htmlspecialchars(substr($sql, 0, 100)) . "...</p>";
        }
    }
}

echo "<hr><p style='color:green;font-weight:bold;'>✅ Migration complete! กรุณาลบไฟล์นี้หลังใช้งานเสร็จ</p>";
echo "<a href='teacher/admin_consultations.php'>→ ไปหน้ากล่องข้อความครู</a>";
echo "</body></html>";
?>
