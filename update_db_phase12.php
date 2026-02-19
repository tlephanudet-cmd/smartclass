<?php
require_once 'includes/config.php';

$queries = [
    // Table: consultations
    "CREATE TABLE IF NOT EXISTS `consultations` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` int(11) DEFAULT NULL,
      `topic_category` varchar(100) NOT NULL,
      `message` text NOT NULL,
      `status` enum('pending','processing','resolved') NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Table: consultation_replies
    "CREATE TABLE IF NOT EXISTS `consultation_replies` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `consultation_id` int(11) NOT NULL,
      `reply_message` text NOT NULL,
      `replied_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `consultation_id` (`consultation_id`),
      FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Table: ai_chat_logs
    "CREATE TABLE IF NOT EXISTS `ai_chat_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` int(11) NOT NULL,
      `user_message` text NOT NULL,
      `ai_response` text NOT NULL,
      `sentiment_score` float DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    // Update site_settings for LINE Token
    "INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES ('line_notify_token', '');",
    
    // Update users for individual LINE Token (Optional)
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `line_token` varchar(255) DEFAULT NULL;"
];

echo "<h2>Updating Database for Tle-Care & AI...</h2>";

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>Executed: " . htmlspecialchars(substr($sql, 0, 50)) . "...</p>";
    } else {
        echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
    }
}

echo "<p>Done! Please delete this file.</p>";
?>
