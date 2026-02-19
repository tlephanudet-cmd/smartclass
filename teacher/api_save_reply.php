<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check Login & Role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';

    if ($consultation_id > 0 && !empty($message)) {
        // INSERT reply with sender_type = 'teacher'
        $stmt = $conn->prepare("INSERT INTO consultation_replies (consultation_id, sender_type, message) VALUES (?, 'teacher', ?)");
        $stmt->bind_param("is", $consultation_id, $message);
        
        if ($stmt->execute()) {
            // Update consultation status to resolved
            $conn->query("UPDATE consultations SET status = 'resolved' WHERE id = $consultation_id");
            echo json_encode(['status' => 'success', 'message' => 'บันทึกสำเร็จ']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Input']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Method']);
}
?>
