<?php
require_once 'includes/config.php';

echo "<h2>Fixing consultation_replies table...</h2><pre>";

// Add sender_type column if not exists
$r1 = $conn->query("SHOW COLUMNS FROM consultation_replies LIKE 'sender_type'");
if ($r1->num_rows == 0) {
    $conn->query("ALTER TABLE consultation_replies ADD COLUMN sender_type ENUM('student','teacher') NOT NULL DEFAULT 'teacher' AFTER consultation_id");
    echo "Added 'sender_type' column ✅\n";
} else {
    echo "'sender_type' column already exists ✅\n";
}

// Add message column if not exists
$r2 = $conn->query("SHOW COLUMNS FROM consultation_replies LIKE 'message'");
if ($r2->num_rows == 0) {
    $conn->query("ALTER TABLE consultation_replies ADD COLUMN message TEXT AFTER sender_type");
    echo "Added 'message' column ✅\n";
    // Copy data from reply_message to message
    $conn->query("UPDATE consultation_replies SET message = reply_message WHERE message IS NULL");
    echo "Copied data from 'reply_message' to 'message' ✅\n";
} else {
    echo "'message' column already exists ✅\n";
}

// Add created_at column if not exists
$r3 = $conn->query("SHOW COLUMNS FROM consultation_replies LIKE 'created_at'");
if ($r3->num_rows == 0) {
    $conn->query("ALTER TABLE consultation_replies ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    // Copy data from replied_at to created_at
    $r4 = $conn->query("SHOW COLUMNS FROM consultation_replies LIKE 'replied_at'");
    if ($r4->num_rows > 0) {
        $conn->query("UPDATE consultation_replies SET created_at = replied_at WHERE replied_at IS NOT NULL");
        echo "Copied data from 'replied_at' to 'created_at' ✅\n";
    }
    echo "Added 'created_at' column ✅\n";
} else {
    echo "'created_at' column already exists ✅\n";
}

echo "\n--- Final Structure ---\n";
$result = $conn->query("DESCRIBE consultation_replies");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . " | Default: " . $row['Default'] . "\n";
}
echo "</pre>";
?>
