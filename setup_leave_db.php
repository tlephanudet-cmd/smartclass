<?php
require_once 'includes/config.php';

// Create leave_requests table
$sql = "CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `leave_type` enum('sick','business') NOT NULL DEFAULT 'sick',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'leave_requests' OK ✅<br>";
} else {
    echo "Error: " . $conn->error . "<br>";
}

// Create uploads/leaves directory
$dir = __DIR__ . '/uploads/leaves';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
    echo "Directory 'uploads/leaves' created ✅<br>";
} else {
    echo "Directory 'uploads/leaves' already exists ✅<br>";
}
?>
