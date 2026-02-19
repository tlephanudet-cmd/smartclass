<?php
// Ensure consultation_replies table exists with correct schema
require_once 'includes/config.php';

$sql = "CREATE TABLE IF NOT EXISTS `consultation_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consultation_id` int(11) NOT NULL,
  `sender_type` enum('student','teacher') NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `consultation_id` (`consultation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'consultation_replies' OK";
} else {
    echo "Error: " . $conn->error;
}
?>
