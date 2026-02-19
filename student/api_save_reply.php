<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check Login & Role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    $student_id = $_SESSION['student_id'];

    if ($consultation_id > 0 && !empty($message)) {
        // Verify this consultation belongs to the student
        $check = $conn->prepare("SELECT id FROM consultations WHERE id = ? AND student_id = ?");
        $check->bind_param("ii", $consultation_id, $student_id);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ไม่พบรายการปรึกษา']);
            exit;
        }

        // INSERT reply — write to BOTH message AND reply_message for compatibility
        $stmt = $conn->prepare("INSERT INTO consultation_replies (consultation_id, sender_type, message, reply_message) VALUES (?, 'student', ?, ?)");
        $stmt->bind_param("iss", $consultation_id, $message, $message);
        
        if ($stmt->execute()) {
            // Re-open for teacher attention
            $conn->query("UPDATE consultations SET status = 'pending' WHERE id = $consultation_id");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกข้อความ']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Method']);
}
?>
