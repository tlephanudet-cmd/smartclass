<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check Login & Role (Teacher only)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';

    if ($consultation_id > 0 && !empty($message)) {
        // INSERT reply — write to BOTH message AND reply_message for compatibility
        $stmt = $conn->prepare("INSERT INTO consultation_replies (consultation_id, sender_type, message, reply_message) VALUES (?, 'teacher', ?, ?)");
        $stmt->bind_param("iss", $consultation_id, $message, $message);
        
        if ($stmt->execute()) {
            // Update consultation status to 'resolved'
            $conn->query("UPDATE consultations SET status = 'resolved' WHERE id = $consultation_id");
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'บันทึกสำเร็จ',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อความ']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Method']);
}
?>
