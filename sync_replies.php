<?php
require_once 'includes/config.php';

// Sync: copy reply_message -> message where message is null
$conn->query("UPDATE consultation_replies SET message = reply_message WHERE message IS NULL OR message = ''");
echo "Data synced: reply_message -> message ✅\n";

// Sync: set sender_type = 'teacher' where it's default/null (old data was from teachers)
$conn->query("UPDATE consultation_replies SET sender_type = 'teacher' WHERE sender_type IS NULL OR sender_type = ''");
echo "Data synced: sender_type set to 'teacher' for old records ✅\n";

// Verify
echo "\n--- All records ---\n";
$result = $conn->query("SELECT id, consultation_id, sender_type, LEFT(message,50) as msg_preview, LEFT(reply_message,50) as reply_preview FROM consultation_replies ORDER BY id");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | CID: {$row['consultation_id']} | Type: {$row['sender_type']} | Msg: {$row['msg_preview']} | OldReply: {$row['reply_preview']}\n";
}
?>
