<?php
require_once 'includes/config.php';

$queries = [
    "CREATE TABLE IF NOT EXISTS `mood_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` int(11) NOT NULL,
      `mood_level` tinyint(4) NOT NULL,
      `note` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `student_id` (`student_id`),
      FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `learning_resources` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(255) NOT NULL,
      `file_type` enum('pdf','link','video') NOT NULL,
      `file_path` varchar(255) NOT NULL,
      `download_count` int(11) DEFAULT 0,
      `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

echo "<h2>Updating Database...</h2>";

foreach ($queries as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>Successfully executed: " . htmlspecialchars(substr($sql, 0, 50)) . "...</p>";
    } else {
        echo "<p style='color: red;'>Error: " . $conn->error . "</p>";
    }
}

echo "<p>Done! Please delete this file.</p>";
?>
